<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;

class CertificateMailer
{
    /**
     * Send a generated certificate using Laravel's configured mailer.
     * Returns [success, message] so the migrated V4 UI can keep its existing
     * success/error popup behaviour.
     */
    public function send(array $certificateRow, string $absolutePath): array
    {
        $to = trim((string) ($certificateRow['email'] ?? ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Participant email is missing or invalid.'];
        }

        if (!is_file($absolutePath)) {
            return [false, 'Generated certificate file was not found.'];
        }

        $fromEmail = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', 'TrainHub Al Amin'));
        if (!filter_var($fromEmail, FILTER_VALIDATE_EMAIL)) {
            return [false, 'Certificate email is not configured. Set MAIL_FROM_ADDRESS and SMTP settings in .env.'];
        }

        $participantName = trim((string) ($certificateRow['participantName'] ?? 'Participant'));
        $courseName = trim((string) ($certificateRow['courseName'] ?? 'Training Course'));
        $trainingDate = !empty($certificateRow['sessionDate'])
            ? date('d M Y', strtotime((string) $certificateRow['sessionDate']))
            : 'Not specified';
        $trainingTime = (!empty($certificateRow['startTime']) && !empty($certificateRow['endTime']))
            ? date('h:i A', strtotime((string) $certificateRow['startTime']))
                .' - '.date('h:i A', strtotime((string) $certificateRow['endTime']))
            : 'Not specified';

        $subject = 'Your Training Certificate - '.$courseName;
        $body = "Assalamualaikum / Greetings {$participantName},\n\n"
            ."Your certificate for {$courseName} is attached with this email.\n\n"
            ."Training: {$courseName}\n"
            ."Date: {$trainingDate}\n"
            ."Time: {$trainingTime}\n\n"
            ."Please keep this certificate for your record.\n\n"
            ."Thank you.\nTrainHub Al Amin";

        $extension = strtolower(pathinfo($absolutePath, PATHINFO_EXTENSION)) ?: 'png';
        $safeName = preg_replace('/[^A-Za-z0-9 _.-]+/', '', $participantName.'_'.$courseName.'_Certificate') ?: 'certificate';
        $filename = trim(str_replace(' ', '_', $safeName), '._').'.'.$extension;
        $mime = function_exists('mime_content_type')
            ? (mime_content_type($absolutePath) ?: 'application/octet-stream')
            : 'application/octet-stream';

        try {
            Mail::raw($body, function ($message) use ($to, $fromEmail, $fromName, $subject, $absolutePath, $filename, $mime): void {
                $message->from($fromEmail, $fromName)
                    ->to($to)
                    ->subject($subject)
                    ->attach($absolutePath, [
                        'as' => $filename,
                        'mime' => $mime,
                    ]);
            });

            return [true, 'Sent successfully.'];
        } catch (\Throwable $e) {
            report($e);
            return [false, 'Unable to send certificate email: '.$e->getMessage()];
        }
    }
}
