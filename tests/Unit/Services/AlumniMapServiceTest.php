<?php

namespace Tests\Unit\Services;

use App\Services\AlumniMapService;
use PHPUnit\Framework\TestCase;

/** Viewport slicing and grid aggregation of the alumni location map's cached dataset (pure PHP, no database). */
class AlumniMapServiceTest extends TestCase
{
    private function loc(string $city, float $lat, float $lng, int $count = 10, int $employed = 4): array
    {
        return ['city' => $city, 'province' => 'Laguna', 'lat' => $lat, 'lng' => $lng, 'count' => $count, 'employed' => $employed, 'top_courses' => []];
    }

    private function dataset(array $locations): array
    {
        return ['locations' => $locations, 'unmapped' => ['locations' => 0, 'alumni' => 0]];
    }

    public function testNoBoundsReturnsEveryLocationAsPoints(): void
    {
        $ds = $this->dataset([$this->loc('A', 14.1, 121.1), $this->loc('B', 14.2, 121.2)]);

        $out = AlumniMapService::slice($ds, null, 10);

        $this->assertSame('points', $out['mode']);
        $this->assertSame(2, $out['in_view']);
        $this->assertCount(2, $out['locations']);
    }

    public function testBoundsKeepOnlyLocationsInsideTheViewport(): void
    {
        $ds = $this->dataset([
            $this->loc('inside', 14.15, 121.15),
            $this->loc('north-of-view', 15.5, 121.15),
            $this->loc('west-of-view', 14.15, 119.0),
            $this->loc('on-the-edge', 14.3, 121.3),
        ]);

        $out = AlumniMapService::slice($ds, ['north' => 14.3, 'south' => 14.0, 'east' => 121.3, 'west' => 121.0], 11);

        $this->assertSame(['inside', 'on-the-edge'], array_column($out['locations'], 'city'));
        $this->assertSame(2, $out['in_view']);
    }

    public function testTooManyPointsAreMergedIntoCellsThatKeepTheTotals(): void
    {
        $locations = [];
        for ($i = 0; $i < 600; $i++) {
            $locations[] = $this->loc("L{$i}", 13.5 + ($i % 30) * 0.04, 120.9 + intdiv($i, 30) * 0.04, 7, 3);
        }
        $ds = $this->dataset($locations);

        $out = AlumniMapService::slice($ds, null, 8, 100);

        $this->assertSame('aggregate', $out['mode']);
        $this->assertSame(600, $out['in_view']);
        $this->assertLessThan(600, count($out['locations']));
        $this->assertSame(600 * 7, array_sum(array_column($out['locations'], 'count')));
        $this->assertSame(600 * 3, array_sum(array_column($out['locations'], 'employed')));
        $this->assertSame(600, array_sum(array_column($out['locations'], 'locations')));
    }

    public function testZoomingInSplitsCellsApart(): void
    {
        $locations = [];
        for ($i = 0; $i < 400; $i++) {
            $locations[] = $this->loc("L{$i}", 13.5 + ($i % 20) * 0.05, 120.9 + intdiv($i, 20) * 0.05);
        }

        $wide = AlumniMapService::aggregate($locations, 6);
        $close = AlumniMapService::aggregate($locations, 12);

        $this->assertGreaterThan(count($wide), count($close));
    }

    public function testCellCentroidIsWeightedByAlumniCount(): void
    {
        // Same cell at a coarse zoom: 9 alumni at lat 14.0 and 1 alumnus at lat 14.1 => centroid 14.01, not 14.05.
        $cells = AlumniMapService::aggregate([
            $this->loc('big', 14.0, 121.0, 9, 1),
            $this->loc('small', 14.1, 121.0, 1, 0),
        ], 2);

        $this->assertCount(1, $cells);
        $this->assertEqualsWithDelta(14.01, $cells[0]['lat'], 0.0001);
        $this->assertSame(10, $cells[0]['count']);
    }
}
