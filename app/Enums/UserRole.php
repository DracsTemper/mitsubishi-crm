<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Dealer = 'dealer';
    case Salesman = 'salesman';
    case Customer = 'customer';

    public function dashboardRouteName(): string
    {
        return $this->value.'.dashboard';
    }
}
