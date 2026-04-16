<?php
/**
 * Attendance Notification Mailer
 * Sends email to guardian when a student's attendance status changes.
 *
 * Requires environment variables:
 *   MAIL_HOST       - SMTP host         (e.g. smtp.gmail.com)
 *   MAIL_PORT       - SMTP port         (e.g. 587)
 *   MAIL_USERNAME   - SMTP username / sender address
 *   MAIL_PASSWORD   - SMTP password / app-password
 *   MAIL_FROM_NAME  - Sender display name (default: "ACLC Attendance")
 *   MAIL_ENCRYPTION - tls | ssl | none  (default: tls)
 */

function sendAttendanceNotification(
    string $guardianEmail,
    string $studentName,
    string $newStatus,
    string $date,
    string $timeIn   = '',
    string $remarks  = ''
): bool {
    $host       = getenv('MAIL_HOST')       ?: '';
    $port       = (int)(getenv('MAIL_PORT') ?: 587);
    $username   = getenv('MAIL_USERNAME')   ?: '';
    $password   = getenv('MAIL_PASSWORD')   ?: '';
    $fromName   = getenv('MAIL_FROM_NAME')  ?: 'ACLC Attendance System';
    $encryption = strtolower(getenv('MAIL_ENCRYPTION') ?: 'tls');

    // If SMTP is not configured, log and bail gracefully
    if (!$host || !$username || !$password) {
        error_log("[Mailer] SMTP not configured — skipping notification to $guardianEmail");
        return false;
    }

    if (!filter_var($guardianEmail, FILTER_VALIDATE_EMAIL)) {
        error_log("[Mailer] Invalid guardian email: $guardianEmail");
        return false;
    }

    $statusLabels = [
        'present' => 'Present',
        'absent'  => 'Absent',
        'late'    => 'Late / Tardy',
        'missing' => 'Missing',
    ];
    $statusDisplay = $statusLabels[strtolower($newStatus)] ?? ucfirst($newStatus);

    $statusColors = [
        'present' => '#22c55e',
        'absent'  => '#ef4444',
        'late'    => '#f97316',
        'missing' => '#a855f7',
    ];
    $color = $statusColors[strtolower($newStatus)] ?? '#6b7280';

    $formattedDate = date('F j, Y', strtotime($date));
    $timeDisplay   = $timeIn ? htmlspecialchars($timeIn) : '—';
    $remarksHtml   = $remarks ? '<p style="margin:0 0 8px"><strong>Remarks:</strong> ' . htmlspecialchars($remarks) . '</p>' : '';

    $subject = "Attendance Update: {$studentName} — {$statusDisplay} on {$formattedDate}";

    $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:32px 0;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.1);">

      <!-- Header -->
      <tr><td style="background:#003087;padding:24px 32px;">
        <h1 style="margin:0;color:#ffffff;font-size:20px;font-weight:700;">ACLC Attendance Monitoring</h1>
        <p style="margin:4px 0 0;color:#93c5fd;font-size:13px;">Automated Notification</p>
      </td></tr>

      <!-- Body -->
      <tr><td style="padding:32px;">
        <p style="margin:0 0 16px;font-size:15px;color:#374151;">Dear Guardian,</p>
        <p style="margin:0 0 24px;font-size:15px;color:#374151;">
          The attendance status of <strong>{$studentName}</strong> has been updated.
          Please see the details below.
        </p>

        <!-- Status badge -->
        <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;">
          <tr><td style="padding:20px 24px;">
            <table width="100%" cellpadding="0" cellspacing="0">
              <tr>
                <td style="font-size:13px;color:#6b7280;padding-bottom:4px;">STATUS</td>
                <td align="right">
                  <span style="background:{$color};color:#fff;padding:4px 12px;border-radius:20px;font-size:13px;font-weight:700;">{$statusDisplay}</span>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="border-top:1px solid #e5e7eb;padding-top:12px;"></td>
              </tr>
              <tr>
                <td style="font-size:14px;color:#374151;padding-bottom:8px;"><strong>Student</strong></td>
                <td align="right" style="font-size:14px;color:#374151;">{$studentName}</td>
              </tr>
              <tr>
                <td style="font-size:14px;color:#374151;padding-bottom:8px;"><strong>Date</strong></td>
                <td align="right" style="font-size:14px;color:#374151;">{$formattedDate}</td>
              </tr>
              <tr>
                <td style="font-size:14px;color:#374151;"><strong>Time In</strong></td>
                <td align="right" style="font-size:14px;color:#374151;">{$timeDisplay}</td>
              </tr>
            </table>
          </td></tr>
        </table>

        {$remarksHtml}

        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;">
          If you have any concerns, please contact the school directly.
        </p>
        <p style="margin:0;font-size:13px;color:#6b7280;">
          This is an automated message. Please do not reply.
        </p>
      </td></tr>

      <!-- Footer -->
      <tr><td style="background:#f9fafb;padding:16px 32px;border-top:1px solid #e5e7eb;">
        <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
          &copy; <?= date('Y') ?> ACLC College. All rights reserved.
        </p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    // Build SMTP socket
    $socketAddr = ($encryption === 'ssl')
        ? "ssl://{$host}:{$port}"
        : "tcp://{$host}:{$port}";

    $errno  = 0;
    $errstr = '';
    $socket = @stream_socket_client($socketAddr, $errno, $errstr, 10);
    if (!$socket) {
        error_log("[Mailer] Socket connect failed ({$socketAddr}): {$errstr}");
        return false;
    }

    stream_set_timeout($socket, 10);

    $read = function() use ($socket): string {
        return fgets($socket, 515);
    };
    $send = function(string $cmd) use ($socket): void {
        fwrite($socket, $cmd . "\r\n");
    };

    try {
        $read(); // 220 banner

        $send("EHLO " . gethostname());
        // Read multi-line EHLO
        while (true) {
            $line = $read();
            if (!$line || substr($line, 3, 1) === ' ') break;
        }

        if ($encryption === 'tls') {
            $send("STARTTLS");
            $read(); // 220
            stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            $send("EHLO " . gethostname());
            while (true) {
                $line = $read();
                if (!$line || substr($line, 3, 1) === ' ') break;
            }
        }

        $send("AUTH LOGIN");
        $read();
        $send(base64_encode($username));
        $read();
        $send(base64_encode($password));
        $authResp = $read();
        if (strpos($authResp, '235') === false) {
            error_log("[Mailer] AUTH failed: $authResp");
            fclose($socket);
            return false;
        }

        $send("MAIL FROM:<{$username}>");
        $read();
        $send("RCPT TO:<{$guardianEmail}>");
        $rcptResp = $read();
        if (strpos($rcptResp, '250') === false) {
            error_log("[Mailer] RCPT rejected: $rcptResp");
            fclose($socket);
            return false;
        }

        $send("DATA");
        $read(); // 354

        $headers  = "From: {$fromName} <{$username}>\r\n";
        $headers .= "To: {$guardianEmail}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: ACLC-Attendance/2.0\r\n";

        fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
        $dataResp = $read();
        if (strpos($dataResp, '250') === false) {
            error_log("[Mailer] DATA rejected: $dataResp");
            fclose($socket);
            return false;
        }

        $send("QUIT");
        fclose($socket);
        return true;

    } catch (\Throwable $e) {
        error_log("[Mailer] Exception: " . $e->getMessage());
        if (is_resource($socket)) fclose($socket);
        return false;
    }
}
