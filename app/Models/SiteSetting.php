<?php

namespace App\Models;

use App\Concerns\LegacyQueries;

/** Ported from backend/Models/SiteSetting.php — see User.php's docblock for the porting approach. */
class SiteSetting
{
    use LegacyQueries;

    private const DEFAULTS = [
        'landing_hero_badge' => 'Official LSPU Employment Platform',
        'landing_hero_headline' => 'Welcome to LSPU-EIS',
        'landing_hero_subtext' => 'The official Employment Information System of Laguna State Polytechnic University. Connecting our talented alumni with exceptional career opportunities worldwide.',
        'landing_hero_image' => '',
        'password_min_length' => '10',
        'password_require_uppercase' => '1',
        'password_require_number' => '1',
        'password_require_symbol' => '1',
    ];

    /** Admin/superadmin password policy, cast to the types callers actually need. */
    public function passwordPolicy(): array
    {
        $settings = $this->all();

        return [
            'min_length' => max(8, (int) $settings['password_min_length']),
            'require_uppercase' => $settings['password_require_uppercase'] === '1',
            'require_number' => $settings['password_require_number'] === '1',
            'require_symbol' => $settings['password_require_symbol'] === '1',
        ];
    }

    public function all(): array
    {
        $settings = self::DEFAULTS;

        foreach ($this->selectAll('SELECT setting_key, setting_value FROM site_settings') as $row) {
            if (array_key_exists($row['setting_key'], $settings)) {
                $settings[$row['setting_key']] = $row['setting_value'];
            }
        }

        return $settings;
    }

    public function save(array $settings): void
    {
        foreach ($settings as $key => $value) {
            $this->insert('INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?', [$key, $value, $value]);
        }
    }
}
