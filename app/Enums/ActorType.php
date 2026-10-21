<?php

namespace App\Enums;

enum ActorType: string
{
    case Admin = 'admin';
    case Participant = 'participant';
    case System = 'system';
}
