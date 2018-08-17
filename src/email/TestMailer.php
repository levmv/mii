<?php

namespace mii\email;

use mii\core\Component;

class TestMailer extends Component
{
    protected $path;

    public function send($to, $name, $subject, $body)
    {
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