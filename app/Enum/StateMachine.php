<?php

namespace App\Enum;

enum StateMachine: string
{
case idle = 'IDLE';
case running = 'RUNNING';
case paused = 'PAUSED';
case pause = 'PAUSE';
case cooldown = 'COOLDOWN';
case completed = 'COMPLETED';

}
