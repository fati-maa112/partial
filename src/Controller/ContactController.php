<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ContactController extends AbstractController
{
    // ── Page route ──────────────────────────────────────────────────────────
    #[Route('/contact', name: 'app_contact', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('contact/index.html.twig');
    }

    // ── Form submit route ────────────────────────────────────────────────────
    //
    // Called by fetch() in contact.html.twig via POST /contact/submit.
    // Validates input, then forwards the message to Brevo (Sendinblue)
    // Transactional Email API (v3) as a plain-text + HTML email.
    //
    // ENV variables required in .env.local:
    //   BREVO_API_KEY=your-brevo-api-key
    //   BREVO_SENDER_EMAIL=hello@naturae.ph
    //   BREVO_SENDER_NAME=Naturaé Skincare
    //   CONTACT_RECIPIENT_EMAIL=hello@naturae.ph
    //   CONTACT_RECIPIENT_NAME=Naturaé Team
    //
    #[Route('/contact/submit', name: 'app_contact_submit', methods: ['POST'])]
    public function submit(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        // ── 1. Only accept JSON XHR requests ────────────────────────────────
        if (!$request->isXmlHttpRequest()) {
            return $this->json(['success' => false, 'message' => 'Bad request.'], 400);
        }

        // ── 2. Decode & sanitise input ───────────────────────────────────────
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Invalid payload.'], 400);
        }

        $firstName = trim(strip_tags($data['firstName'] ?? ''));
        $lastName  = trim(strip_tags($data['lastName']  ?? ''));
        $email     = trim($data['email']   ?? '');
        $subject   = trim(strip_tags($data['subject']  ?? ''));
        $message   = trim(strip_tags($data['message']  ?? ''));

        // ── 3. Server-side validation ────────────────────────────────────────
        $errors = [];

        if (strlen($firstName) < 2) {
            $errors[] = 'First name is required.';
        }
        if (strlen($lastName) < 2) {
            $errors[] = 'Last name is required.';
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (empty($subject)) {
            $errors[] = 'Please select a topic.';
        }
        if (strlen($message) < 10) {
            $errors[] = 'Message must be at least 10 characters.';
        }

        if (!empty($errors)) {
            return $this->json([
                'success' => false,
                'message' => implode(' ', $errors),
            ], 422);
        }

        // ── 4. Build email content ───────────────────────────────────────────
        $senderEmail    = $_ENV['BREVO_SENDER_EMAIL']      ?? 'hello@naturae.ph';
        $senderName     = $_ENV['BREVO_SENDER_NAME']       ?? 'Naturaé Skincare';
        $recipientEmail = $_ENV['CONTACT_RECIPIENT_EMAIL'] ?? 'hello@naturae.ph';
        $recipientName  = $_ENV['CONTACT_RECIPIENT_NAME']  ?? 'Naturaé Team';
        $apiKey         = $_ENV['BREVO_API_KEY']           ?? '';

        $fullName    = $firstName . ' ' . $lastName;
        $emailSubject = '[Contact Form] ' . $subject . ' — ' . $fullName;

        $htmlBody = <<<HTML
        <div style="font-family:Georgia,serif;max-width:600px;margin:0 auto;color:#3c3428;">
            <div style="background:#4a5240;padding:28px 32px;border-radius:0;">
                <h1 style="color:#fff;font-size:1.5rem;font-weight:300;margin:0;letter-spacing:-0.01em;">
                    Naturaé — New Contact Message
                </h1>
            </div>
            <div style="background:#faf8f5;padding:32px;border:1px solid rgba(183,155,127,0.20);">
                <table style="width:100%;border-collapse:collapse;margin-bottom:24px;">
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;color:#9a7a5f;width:120px;">Name</td>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.95rem;">{$fullName}</td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;color:#9a7a5f;">Email</td>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.95rem;"><a href="mailto:{$email}" style="color:#5f6b4d;">{$email}</a></td>
                    </tr>
                    <tr>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;color:#9a7a5f;">Topic</td>
                        <td style="padding:10px 0;border-bottom:1px solid rgba(183,155,127,0.15);font-size:0.95rem;">{$subject}</td>
                    </tr>
                </table>
                <p style="font-size:0.75rem;letter-spacing:0.12em;text-transform:uppercase;color:#9a7a5f;margin-bottom:8px;">Message</p>
                <div style="background:#fff;border:1px solid rgba(183,155,127,0.18);padding:20px 24px;font-size:0.95rem;line-height:1.8;white-space:pre-wrap;">{$message}</div>
            </div>
            <div style="padding:16px 32px;font-size:0.72rem;color:#b09a84;text-align:center;">
                Naturaé Skincare · hello@naturae.ph · Cebu City, Philippines
            </div>
        </div>
        HTML;

        // ── 5. Call Brevo Transactional Email API ────────────────────────────
        try {
            $response = $httpClient->request('POST', 'https://api.brevo.com/v3/smtp/email', [
                'headers' => [
                    'api-key'       => $apiKey,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => [
                    'sender'      => ['name' => $senderName, 'email' => $senderEmail],
                    'to'          => [['name' => $recipientName, 'email' => $recipientEmail]],
                    'replyTo'     => ['name' => $fullName, 'email' => $email],
                    'subject'     => $emailSubject,
                    'htmlContent' => $htmlBody,
                    'textContent' => "New contact form submission\n\nName: {$fullName}\nEmail: {$email}\nTopic: {$subject}\n\nMessage:\n{$message}",
                ],
            ]);

            $statusCode = $response->getStatusCode();

            // Brevo returns 201 Created on success
            if ($statusCode === 201 || $statusCode === 200) {
                return $this->json(['success' => true]);
            }

            // Unexpected 2xx or error from Brevo
            $body = $response->toArray(false);
            $brevoMsg = $body['message'] ?? 'Email service returned an unexpected response.';

            return $this->json([
                'success' => false,
                'message' => 'Could not send your message right now. ' . $brevoMsg,
            ], 502);

        } catch (\Throwable $e) {
            // Log the real error server-side, return generic message to client
            // In production, inject a LoggerInterface and call $logger->error(...)
            return $this->json([
                'success' => false,
                'message' => 'An internal error occurred. Please try again or email us directly.',
            ], 500);
        }
    }
}
