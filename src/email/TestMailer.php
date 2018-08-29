<?php

namespace mii\email;

class TestMailer extends Mailer
{
    protected $path;

    public function send($to = null, $name = null, $subject = null, $body = null) {

        parent::send($to, $name, $subject, $body);

        $this->path = \Mii::resolve($this->path);

        if (!is_writable($this->path)) {
            \Mii::error("Can't write to $this->path");
            return false;
        }

        $text = "to: $to\nname: $name\nsubject: $subject\nbody: $body";

        file_put_contents($this->path . '/' . microtime(true), $text);

        return true;
    }
}