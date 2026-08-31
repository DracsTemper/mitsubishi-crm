<?php

namespace Tests\Unit;

use App\Enums\UserRole;
use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserRoleTest extends TestCase
{
    public function test_it_defines_exactly_the_supported_roles(): void
    {
        $this->assertSame(
            ['admin', 'dealer', 'salesman', 'customer'],
            array_column(UserRole::cases(), 'value'),
        );
    }

    public function test_user_casts_its_role_to_the_enum(): void
    {
        $user = new User();
        $user->setRawAttributes(['role' => 'dealer']);

        $this->assertSame(UserRole::Dealer, $user->role);
    }
}
