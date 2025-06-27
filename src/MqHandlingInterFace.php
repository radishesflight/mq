<?php

namespace RadishesFlight\Mq;

interface MqHandlingInterFace
{
    public function handle($message);
}
