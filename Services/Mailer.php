<?php

namespace Fast\Back\Services;

use Fast\Back\Helpers\Mail;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $config = Mail::get();

        $this->mailer = new PHPMailer(true);
        $this->mailer->isSMTP();
        $this->mailer->Host = $config['host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $config['username'];
        $this->mailer->Password = $config['password'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = $config['port'];
        $this->mailer->CharSet = 'UTF-8';
        $this->mailer->setFrom($config['from_address'], $config['from_name']);
        $this->mailer->isHTML(true);
    }

    public function send(string $toAddress, string $toName, string $subject, string $body): bool
    {
        try {
            $this->mailer->addAddress($toAddress, $toName);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }
}