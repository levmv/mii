<?php

namespace mii\email;

use mii\core\Component;
use mii\web\Block;
use PHPMailer\PHPMailer\PHPMailer as Mailer;

class PHPMailer extends Component
{

    /**
     * @var \PHPMailer
     */
    public $mailer;

    protected $transport = 'smtp';
    protected $config;

    protected $from_mail;
    protected $from_name = '';


    public function init(array $config = []): void {

        foreach ($config as $key => $value) {
            $this->$key = $value;
        }

        $this->mailer = new Mailer(true);

        $this->mailer->CharSet = 'UTF-8';
        if ($this->from_mail) {
            $this->mailer->setFrom($this->from_mail, $this->from_name);
        }


        if ($this->transport === 'sendmail') {
            $this->mailer->isSendmail();
        }

        if ($this->transport === 'smtp') {
            $this->mailer->isSMTP();
        }

        foreach ($this->config as $key => $value) {
            $this->mailer->$key = $value;
        }
    }


    public function send($to, $name, $subject, $body) {

        try {
            $this->mailer->addAddress($to, $name);
            $this->mailer->Subject = $subject;

            if ($body instanceof Block) {
                $html = $body->render(true);
                $path = \Mii::$app->blocks->assets_dir . $body->path() . '/';
                $this->mailer->msgHTML($html, $path);
            } else {

                $this->mailer->Body = $body;
            }

            $result = $this->mailer->send();

            $this->mailer->clearAllRecipients();

        } catch (\Throwable $t) {

            $result = false;
        }

        return $result;

    }

}