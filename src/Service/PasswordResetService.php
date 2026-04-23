<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Asset\Packages;

class PasswordResetService
{
    private FilesystemAdapter $cache;

    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoggerInterface $logger,
        private readonly HttpClientInterface $client,
        private readonly string $mailerFrom,
        private readonly string $wpUrl,
        private readonly string $wpApiSecret,
        private readonly Packages $assetPackages
    ) {
        $this->cache = new FilesystemAdapter('password_reset', 3600);
    }

    public function generateResetToken(string $email): string
    {
        $rateLimitKey  = 'rate_' . md5($email);
        $rateLimitItem = $this->cache->getItem($rateLimitKey);
        $attempts      = $rateLimitItem->isHit() ? ($rateLimitItem->get() ?? []) : [];
        $attempts      = array_values(array_filter($attempts, fn($t) => $t > (time() - 3600)));

        if (count($attempts) >= 3) {
            throw new \RuntimeException('Trop de tentatives de réinitialisation. Veuillez réessayer dans une heure.');
        }

        $token     = bin2hex(random_bytes(32));
        $cacheItem = $this->cache->getItem('reset_' . $token);
        $cacheItem->set(['email' => $email, 'created_at' => time(), 'used' => false]);
        $cacheItem->expiresAfter(3600);
        $this->cache->save($cacheItem);

        $attempts[] = time();
        $rateLimitItem->set($attempts);
        $rateLimitItem->expiresAfter(3600);
        $this->cache->save($rateLimitItem);

        return $token;
    }

    public function validateResetToken(string $token): ?string
    {
        $cacheItem = $this->cache->getItem('reset_' . $token);

        if (!$cacheItem->isHit()) {
            return null;
        }

        $data = $cacheItem->get();

        if ($data['used'] ?? false) {
            return null;
        }

        if (time() - ($data['created_at'] ?? 0) > 3600) {
            $this->cache->deleteItem('reset_' . $token);
            return null;
        }

        return $data['email'] ?? null;
    }

    public function invalidateToken(string $token): void
    {
        $this->cache->deleteItem('reset_' . $token);
    }

    public function checkUserExists(string $email): bool
    {
        try {
            $response = $this->client->request('POST',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/user-exists',
                [
                    'headers' => ['X-Sevra-Secret' => $this->wpApiSecret, 'Content-Type' => 'application/json'],
                    'json'    => ['email' => $email],
                    'timeout' => 5,
                ]
            );
            $data = $response->toArray();
            return (bool) ($data['exists'] ?? false);
        } catch (\Exception $e) {
            $this->logger->error('checkUserExists failed: ' . $e->getMessage());
            return false;
        }
    }

    public function updateWordPressPassword(string $email, string $password): bool
    {
        try {
            $response = $this->client->request('POST',
                rtrim($this->wpUrl, '/') . '/wp-json/sevra/v1/update-password',
                [
                    'headers' => ['X-Sevra-Secret' => $this->wpApiSecret, 'Content-Type' => 'application/json'],
                    'json'    => ['email' => $email, 'password' => $password],
                    'timeout' => 10,
                ]
            );
            $success = $response->getStatusCode() === 200;
            $this->logger->info('WP password update', ['status' => $response->getStatusCode()]);
            return $success;
        } catch (\Exception $e) {
            $this->logger->error('updateWordPressPassword failed: ' . $e->getMessage());
            return false;
        }
    }

    public function sendResetEmail(string $email, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate(
            'reset_password_form',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL
        );

        // Build absolute logo URL using the router — works in dev (localhost) and prod automatically
        $baseUrl = $this->urlGenerator->generate('home', [], UrlGeneratorInterface::ABSOLUTE_URL);
        $logoUrl = rtrim($baseUrl, '/') . '/' . ltrim($this->assetPackages->getUrl('images/SevraLogo.png'), '/');

        $emailMessage = (new Email())
            ->from($this->mailerFrom)
            ->to($email)
            ->subject('Réinitialisation de votre mot de passe — Sevra Collection')
            ->html($this->renderEmailTemplate($resetUrl, $email, $logoUrl))
            ->replyTo('contact@sevracollection.com');

        try {
            $this->mailer->send($emailMessage);
            $this->logger->info('Password reset email sent', ['email' => $email]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send reset email: ' . $e->getMessage());
            throw new \RuntimeException('Failed to send reset email: ' . $e->getMessage());
        }
    }

    private function renderEmailTemplate(string $resetUrl, string $email, string $logoUrl): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="fr" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Réinitialisation de mot de passe — Sevra Collection</title>
</head>
<body style="margin:0;padding:0;background-color:#F7F2EC;font-family:'Georgia',serif;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;">

  <!-- Hidden preheader preview text -->
  <div style="display:none;font-size:1px;line-height:1px;max-height:0;max-width:0;opacity:0;overflow:hidden;mso-hide:all;">
    Réinitialisez votre mot de passe Sevra Collection — lien valable 1 heure.&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;&nbsp;&zwnj;
  </div>

  <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="background-color:#F7F2EC;">
    <tr>
      <td align="center" style="padding:48px 16px 64px;">

        <!-- Email card -->
        <table width="600" cellpadding="0" cellspacing="0" border="0" role="presentation"
               style="max-width:600px;width:100%;background-color:#FDFAF7;border-radius:1px;
                      box-shadow:0 4px 48px rgba(32,24,16,0.10),0 1px 4px rgba(32,24,16,0.06);">

          <!-- Gold gradient top bar -->
          <tr>
            <td style="background:linear-gradient(90deg,#6B5B45 0%,#C4A882 40%,#D4BC98 60%,#8A7455 100%);
                       height:4px;font-size:0;line-height:0;" role="presentation">&nbsp;</td>
          </tr>

          <!-- Logo header -->
          <tr>
            <td align="center" style="padding:52px 48px 40px;">
              <a href="https://sevracollection.com" style="text-decoration:none;display:block;">
                <img src="{$logoUrl}"
                     alt="Sevra Collection"
                     width="160"
                     style="display:block;width:160px;max-width:160px;height:auto;margin:0 auto;border:0;"
                />
              </a>
            </td>
          </tr>

          <!-- Thin divider -->
          <tr>
            <td style="padding:0 48px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="border-top:1px solid rgba(138,116,85,0.18);font-size:0;line-height:0;">&nbsp;</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Hero text -->
          <tr>
            <td align="center" style="padding:48px 48px 8px;">
              <h1 style="margin:0 0 20px;font-family:'Georgia','Times New Roman',serif;
                         font-style:italic;font-weight:400;font-size:30px;line-height:1.25;
                         color:#1C1410;letter-spacing:0.01em;">
                Nouveau mot de passe
              </h1>
              <p style="margin:0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                        font-size:14px;line-height:1.8;color:#7A6E65;letter-spacing:0.01em;
                        max-width:400px;">
                Nous avons reçu une demande de réinitialisation pour le compte associé à
              </p>
              <p style="margin:10px 0 0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                        font-size:14px;font-weight:600;color:#1C1410;letter-spacing:0.02em;">
                {$email}
              </p>
            </td>
          </tr>

          <!-- CTA button -->
          <tr>
            <td align="center" style="padding:40px 48px 36px;">
              <table cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td align="center" bgcolor="#768064"
                      style="border-radius:1px;mso-padding-alt:0;">
                    <a href="{$resetUrl}"
                       style="display:inline-block;padding:17px 48px;
                              font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                              font-size:11px;font-weight:700;letter-spacing:0.20em;
                              text-transform:uppercase;color:#f2e5d9;
                              text-decoration:none;border-radius:1px;
                              mso-padding-alt:17px 48px;">
                      Réinitialiser mon mot de passe
                    </a>
                  </td>
                </tr>
              </table>

              <!-- Expiry badge -->
              <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:22px auto 0;">
                <tr>
                  <td style="background:#F0EAE0;border-radius:20px;padding:6px 16px;">
                    <p style="margin:0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                               font-size:11px;color:#8A7455;letter-spacing:0.06em;text-transform:uppercase;">
                      &#9679;&nbsp; Expire dans 1 heure
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Section divider with label -->
          <tr>
            <td style="padding:0 48px 24px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="border-top:1px solid rgba(138,116,85,0.14);font-size:0;">&nbsp;</td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Fallback link -->
          <tr>
            <td style="padding:0 48px 32px;">
              <p style="margin:0 0 10px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                        font-size:10px;color:#B0A89E;letter-spacing:0.10em;text-transform:uppercase;">
                Lien de secours
              </p>
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="background:#F0EAE0;border-left:2px solid #C4A882;
                             border-radius:1px;padding:13px 16px;">
                    <a href="{$resetUrl}"
                       style="font-family:'Courier New',Courier,monospace;font-size:11px;
                              color:#8A7455;text-decoration:none;word-break:break-all;line-height:1.6;">
                      {$resetUrl}
                    </a>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Security notice -->
          <tr>
            <td style="padding:0 48px 48px;">
              <table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation">
                <tr>
                  <td style="background:#F5F0E8;border-radius:1px;padding:18px 20px;
                             border:1px solid rgba(196,168,130,0.25);">
                    <p style="margin:0 0 6px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                               font-size:10px;font-weight:700;color:#5C4A35;
                               letter-spacing:0.12em;text-transform:uppercase;">
                      &#128274;&nbsp; Avis de sécurité
                    </p>
                    <p style="margin:0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                               font-size:12px;color:#7A6E65;line-height:1.75;">
                      Si vous n'êtes pas à l'origine de cette demande, ignorez cet email — votre mot de passe restera inchangé.
                      Ne partagez jamais ce lien avec qui que ce soit.
                    </p>
                  </td>
                </tr>
              </table>
            </td>
          </tr>

          <!-- Gold gradient bottom bar -->
          <tr>
            <td style="background:linear-gradient(90deg,#6B5B45 0%,#C4A882 40%,#D4BC98 60%,#8A7455 100%);
                       height:1px;font-size:0;line-height:0;" role="presentation">&nbsp;</td>
          </tr>

          <!-- Footer -->
          <tr>
            <td align="center" style="padding:28px 48px 36px;background:#F0EAE0;">
              <p style="margin:0 0 4px;font-family:'Georgia','Times New Roman',serif;
                        font-style:italic;font-size:13px;color:#8A7455;letter-spacing:0.08em;">
                Sevra Collection
              </p>
              <p style="margin:0 0 18px;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                        font-size:10px;color:#B0A89E;letter-spacing:0.06em;text-transform:uppercase;">
                Ensembles en lin pour femme
              </p>

              <!-- Footer links -->
              <table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:0 auto 18px;">
                <tr>
                  <td style="padding:0 14px;border-right:1px solid rgba(138,116,85,0.20);">
                    <a href="https://sevracollection.com"
                       style="font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:10px;
                              color:#8A7455;text-decoration:none;letter-spacing:0.06em;text-transform:uppercase;">
                      Site web
                    </a>
                  </td>
                  <td style="padding:0 14px;border-right:1px solid rgba(138,116,85,0.20);">
                    <a href="https://sevracollection.com/shop"
                       style="font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:10px;
                              color:#8A7455;text-decoration:none;letter-spacing:0.06em;text-transform:uppercase;">
                      Boutique
                    </a>
                  </td>
                  <td style="padding:0 14px;">
                    <a href="mailto:contact@sevracollection.com"
                       style="font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;font-size:10px;
                              color:#8A7455;text-decoration:none;letter-spacing:0.06em;text-transform:uppercase;">
                      Contact
                    </a>
                  </td>
                </tr>
              </table>

              <p style="margin:0;font-family:'Helvetica Neue',Helvetica,Arial,sans-serif;
                        font-size:10px;color:#C0B8B0;letter-spacing:0.03em;">
                &copy; 2026 Sevra Collection &nbsp;&middot;&nbsp; Cet email a été envoyé automatiquement
              </p>
            </td>
          </tr>

        </table>
        <!-- /Email card -->

      </td>
    </tr>
  </table>

</body>
</html>
HTML;
    }
}
