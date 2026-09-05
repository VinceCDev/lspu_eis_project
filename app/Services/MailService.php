<?php

namespace App\Services;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

/**
 * Ported from backend/Services/MailService.php. PHPMailer is loaded via
 * Composer (phpmailer/phpmailer) instead of the original's manual
 * require_once of a vendored copy.
 */
class MailService
{
    public static function wrap(string $heading, string $bodyHtml, ?string $ctaText = null, ?string $ctaUrl = null): string
    {
        $cta = '';
        if ($ctaText !== null && $ctaUrl !== null) {
            $cta = '<a href="'.htmlspecialchars($ctaUrl).'" style="display:block;width:fit-content;background:#00A0E9;color:#ffffff;padding:12px 30px;text-decoration:none;border-radius:6px;margin:28px auto 0;font-weight:bold;font-family:Arial,sans-serif;">'
                .htmlspecialchars($ctaText).'</a>';
        }

        return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>'
            .'<body style="margin:0;padding:0;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;">'
            .'<div style="max-width:600px;margin:0 auto;background:#ffffff;">'
            .'<div style="background:linear-gradient(135deg,#00A0E9 0%,#1A1A1A 100%);color:#ffffff;padding:32px 30px;text-align:center;">'
            .'<h1 style="margin:0;font-size:24px;letter-spacing:0.5px;">LSPU EIS</h1>'
            .'<p style="margin:6px 0 0;font-size:13px;opacity:0.9;">Laguna State Polytechnic University</p>'
            .'<p style="margin:0;font-size:13px;opacity:0.9;">Employment Information System</p>'
            .'<div style="height:3px;width:56px;background:#FFD54F;margin:16px auto 0;border-radius:2px;"></div>'
            .'</div>'
            .'<div style="padding:32px 30px;color:#1A1A1A;">'
            ."<h2 style=\"margin:0 0 16px;color:#1A1A1A;font-size:19px;\">{$heading}</h2>"
            .'<div style="font-size:15px;line-height:1.6;color:#333333;">'.$bodyHtml.'</div>'
            .$cta
            .'</div>'
            .'<div style="text-align:center;margin-top:8px;color:#6b7280;font-size:12px;padding:20px 30px 26px;border-top:1px solid #e5e7eb;">'
            .'<p style="margin:0 0 4px;">This is an automated message from the LSPU Employment Information System.</p>'
            .'<p style="margin:0;">&copy; '.date('Y').' Laguna State Polytechnic University. All rights reserved.</p>'
            .'</div></div></body></html>';
    }

    /**
     * @param array<int, array{content: string, filename: string, mime?: string}> $attachments
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody, string $altBody = '', ?string $replyToEmail = null, ?string $replyToName = null, array $attachments = []): bool
    {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = env('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth = true;
            $mail->Username = env('MAIL_USERNAME', 'lspueis@gmail.com');
            $mail->Password = env('MAIL_PASSWORD', '');
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) env('MAIL_PORT', 587);

            $mail->setFrom('lspueis@gmail.com', 'LSPU EIS');
            $mail->addAddress($toEmail, $toName);
            if ($replyToEmail) {
                $mail->addReplyTo($replyToEmail, $replyToName ?? '');
            }

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $altBody ?: strip_tags($htmlBody);

            foreach ($attachments as $attachment) {
                $mail->addStringAttachment(
                    $attachment['content'],
                    $attachment['filename'],
                    PHPMailer::ENCODING_BASE64,
                    $attachment['mime'] ?? 'application/octet-stream'
                );
            }

            $mail->send();

            return true;
        } catch (Exception $e) {
            error_log('Mailer Error: '.$mail->ErrorInfo);

            return false;
        }
    }
}
