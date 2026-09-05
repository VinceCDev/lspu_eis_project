<?php

namespace Tests\Unit\Core;

use App\Core\Uploader;
use PHPUnit\Framework\TestCase;

/**
 * Pins down the .docx cover-letter bug found during ISO 25010 testing:
 * Uploader::store()/storeNamed() cross-check a file's real content (magic
 * bytes via mime_content_type()) against its extension, but .docx — which
 * is itself a zip archive — is reported as generic application/zip by this
 * environment's magic database rather than the OOXML MIME type, so every
 * .docx upload was silently rejected (store() returned null with no error
 * surfaced to the user) until application/zip was added to the map for the
 * docx extension specifically.
 *
 * Uploader::store() itself can't be unit tested directly — it requires
 * is_uploaded_file() to be true, which is only ever true for a file that
 * arrived via a real HTTP upload — so this test targets the MIME/extension
 * map that decides the outcome instead, via reflection on the private
 * static property.
 */
class UploaderTest extends TestCase
{
    private function mimeToExtensions(): array
    {
        $prop = new \ReflectionProperty(Uploader::class, 'mimeToExtensions');
        $prop->setAccessible(true);

        return $prop->getValue();
    }

    public function testDocxIsAcceptedViaGenericZipMimeType(): void
    {
        $map = $this->mimeToExtensions();

        $this->assertArrayHasKey('application/zip', $map, 'application/zip must map to docx — this is the exact regression that silently broke .docx cover letter uploads');
        $this->assertContains('docx', $map['application/zip']);
    }

    public function testDocxIsAlsoAcceptedViaItsProperOoxmlMimeType(): void
    {
        $map = $this->mimeToExtensions();

        $this->assertArrayHasKey('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $map);
        $this->assertContains('docx', $map['application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
    }

    public function testLegacyDocIsAccepted(): void
    {
        $map = $this->mimeToExtensions();

        $this->assertArrayHasKey('application/msword', $map);
        $this->assertContains('doc', $map['application/msword']);
    }

    public function testGenericZipIsNotMistakenlyAcceptedForOtherExtensions(): void
    {
        // application/zip must ONLY unlock the docx extension — it should
        // not be usable to sneak a renamed .zip past validation as, say, a
        // .pdf or .jpg.
        $map = $this->mimeToExtensions();

        $this->assertSame(['docx'], $map['application/zip']);
    }

    public function testWellFormedDocxIsDetectedByItsProperMimeType(): void
    {
        // A docx with a proper [Content_Types].xml manifest — what real
        // Word/Google Docs exports actually look like — is correctly
        // identified by this environment's libmagic.
        $path = $this->makeZip([
            '[Content_Types].xml' => '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels' => '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>',
            'word/document.xml' => '<xml/>',
        ]);

        $this->assertSame('application/vnd.openxmlformats-officedocument.wordprocessingml.document', mime_content_type($path));

        unlink($path);
    }

    public function testNonOfficeZipRenamedToDocxFallsBackToGenericZipMimeType(): void
    {
        // This is the actual edge case application/zip => ['docx'] guards
        // against: a .docx-extension file whose internal structure has no
        // recognizable Office paths at all (missing/corrupted, or from a
        // non-standard generator) is reported as plain application/zip
        // rather than the OOXML type — libmagic here only recognizes the
        // OOXML type once it sees an internal path like "word/..." or
        // "[Content_Types].xml"; a real Word/Google-Docs export always has
        // these and is unaffected by this fallback (see the test above).
        $path = $this->makeZip(['readme.txt' => 'not an office document']);

        $this->assertSame('application/zip', mime_content_type($path));

        unlink($path);
    }

    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'docxtest').'.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($entries as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        return $path;
    }
}
