<?php

namespace App\Services;

use App\Models\Registration;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Storage;

class QrCodeService
{
    /**
     * Generate pure SVG string of QR code for the given data.
     */
    public function generateSvgString(string $data, int $size = 200, int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd
        );

        $writer = new Writer($renderer);

        return $writer->writeString($data);
    }

    /**
     * Generate and store QR code for a registration record.
     * Encodes ONLY the unique registration code without any sensitive personal data.
     */
    public function generateAndStore(Registration $registration, int $size = 200): string
    {
        $svgContent = $this->generateSvgString($registration->registration_code, $size);
        $fileName = 'qrcodes/'.$registration->registration_code.'.svg';

        Storage::disk('public')->put($fileName, $svgContent);

        $registration->update([
            'qr_code_path' => $fileName,
        ]);

        return $fileName;
    }

    /**
     * Get the SVG content for a registration, generating and storing it if missing.
     */
    public function getOrGenerateSvg(Registration $registration, int $size = 200): string
    {
        if ($registration->qr_code_path && Storage::disk('public')->exists($registration->qr_code_path)) {
            return Storage::disk('public')->get($registration->qr_code_path);
        }

        $this->generateAndStore($registration, $size);

        return Storage::disk('public')->get('qrcodes/'.$registration->registration_code.'.svg');
    }
}
