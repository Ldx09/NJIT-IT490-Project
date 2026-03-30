<?php

require_once __DIR__ . '/notify_config.php';
require_once __DIR__ . '/notify_logger.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

function send_email(string $to_email, string $to_name, string $subject, string $body_html): bool {
    if (!NOTIFY_EMAIL_ENABLED) {
        notify_log("EMAIL (disabled) → {$to_email} | {$subject}");
        return true;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = MAIL_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = MAIL_USERNAME;
        $mail->Password   = MAIL_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = MAIL_PORT;

        $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $mail->addAddress($to_email, $to_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body_html;
        $mail->AltBody = strip_tags($body_html);

        $mail->send();
        notify_log("EMAIL SENT → {$to_email} | {$subject}");
        return true;

    } catch (Exception $e) {
        notify_log("EMAIL FAILED → {$to_email} | {$mail->ErrorInfo}");
        return false;
    }
}

function build_recall_email(array $user, array $car, array $recall): string {
    $name      = htmlspecialchars($user['username']);
    $car_str   = htmlspecialchars("{$car['year']} {$car['make']} {$car['model']}");
    $component = htmlspecialchars($recall['component']);
    $summary   = htmlspecialchars($recall['summary']);
    $nhtsa_id  = htmlspecialchars($recall['nhtsa_id']);
    $date      = htmlspecialchars($recall['recall_date']);

    return <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
      <div style="background:#c0392b;padding:24px;color:#fff;">
        <h2 style="margin:0;">&#9888; Vehicle Recall Alert</h2>
        <p style="margin:6px 0 0;opacity:0.9;">Official NHTSA Recall Notice</p>
      </div>
      <div style="padding:24px;">
        <p>Hi <strong>{$name}</strong>,</p>
        <p>A recall has been issued for your vehicle:</p>
        <div style="background:#f8f8f8;border-left:4px solid #c0392b;padding:16px;border-radius:4px;margin:16px 0;">
          <strong style="font-size:18px;">{$car_str}</strong><br>
          <span style="color:#666;">NHTSA Campaign: {$nhtsa_id}</span>
        </div>
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="padding:8px;border-bottom:1px solid #eee;color:#666;width:140px;">Component</td>
            <td style="padding:8px;border-bottom:1px solid #eee;"><strong>{$component}</strong></td>
          </tr>
          <tr>
            <td style="padding:8px;border-bottom:1px solid #eee;color:#666;">Recall Date</td>
            <td style="padding:8px;border-bottom:1px solid #eee;">{$date}</td>
          </tr>
          <tr>
            <td style="padding:8px;color:#666;vertical-align:top;">Summary</td>
            <td style="padding:8px;">{$summary}</td>
          </tr>
        </table>
        <div style="margin-top:24px;">
          <a href="http://auth.com/fix_it.php?recall_id={$nhtsa_id}"
            style="background:#c0392b;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block;">
            Find a Repair Shop
          </a>
        </div>
        <p style="margin-top:24px;color:#999;font-size:13px;">
          You received this because your vehicle is registered in our system.
        </p>
      </div>
    </div>
    HTML;
}

function build_reminder_email(array $user, array $appt): string {
    $name      = htmlspecialchars($user['username']);
    $shop      = htmlspecialchars($appt['shop_name']);
    $address   = htmlspecialchars($appt['shop_address']);
    $type      = $appt['appt_type'] === 'virtual' ? 'Virtual Appointment' : 'In-Person Appointment';
    $dt        = date('l, F j Y \a\t g:i A', strtotime($appt['appt_datetime']));
    $appt_id   = (int)$appt['id'];

    $link_html = '';
    if ($appt['appt_type'] === 'virtual' && !empty($appt['meeting_link'])) {
        $link = htmlspecialchars($appt['meeting_link']);
        $link_html = "<p style='margin-top:16px;'>
            <a href='{$link}' style='background:#2980b9;color:#fff;padding:10px 20px;border-radius:6px;text-decoration:none;display:inline-block;'>
              Join Virtual Appointment
            </a></p>";
    }

    return <<<HTML
    <div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;border:1px solid #ddd;border-radius:8px;overflow:hidden;">
      <div style="background:#2980b9;padding:24px;color:#fff;">
        <h2 style="margin:0;">&#128337; Appointment Reminder</h2>
        <p style="margin:6px 0 0;opacity:0.9;">Your appointment is tomorrow</p>
      </div>
      <div style="padding:24px;">
        <p>Hi <strong>{$name}</strong>,</p>
        <p>This is a reminder about your upcoming appointment:</p>
        <div style="background:#f0f7ff;border-left:4px solid #2980b9;padding:16px;border-radius:4px;margin:16px 0;">
          <strong style="font-size:17px;">{$type}</strong><br>
          <span style="font-size:15px;color:#333;">{$dt}</span>
        </div>
        <table style="width:100%;border-collapse:collapse;">
          <tr>
            <td style="padding:8px;border-bottom:1px solid #eee;color:#666;width:120px;">Shop</td>
            <td style="padding:8px;border-bottom:1px solid #eee;"><strong>{$shop}</strong></td>
          </tr>
          <tr>
            <td style="padding:8px;color:#666;">Address</td>
            <td style="padding:8px;">{$address}</td>
          </tr>
        </table>
        {$link_html}
        <p style="margin-top:24px;">
          <a href="http://auth.com/schedule.php"
            style="background:#2980b9;color:#fff;padding:12px 24px;border-radius:6px;text-decoration:none;display:inline-block;">
            View My Schedule
          </a>
        </p>
        <p style="color:#999;font-size:13px;">
          <a href="http://auth.com/cancel_appt.php?id={$appt_id}">Cancel this appointment</a>
        </p>
      </div>
    </div>
    HTML;
}
