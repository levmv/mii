<?php

namespace mii\email;

use mii\core\Component;
use mii\core\Exception;
use mii\web\Block;

class PHPMailer extends \mii\email\Mailer
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

        parent::init($config);

        $this->mailer = new PHPMailer\PHPMailer\PHPMailer(true);

        $this->mailer->CharSet = 'UTF-8';

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


    public function send($to = null, $name = null, $subject = null, $body = null) {

        parent::send($to, $name, $subject, $body);

        try {
            foreach($this->to as $address) {
                $this->mailer->addAddress($address[0], $address[1]);
            }

            $this->mailer->Subject = $this->subject;

            if($this->is_html){
                $this->mailer->msgHTML($this->body, $this->assets_path);
            } else {
                $this->mailer->Body = $this->body;
            }

            $result = $this->mailer->send();

            $this->mailer->clearAllRecipients();

        } catch (\Throwable $t) {
            \Mii::error(Exception::text($t));
            $result = false;
        }

        return $result;
    }

}