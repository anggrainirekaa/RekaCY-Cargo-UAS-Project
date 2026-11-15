<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function view(User $user, SupportTicket $t): bool
    {
        return $user->isStaff() || $t->user_id === $user->id;
    }

    public function reply(User $user, SupportTicket $t): bool
    {
        return $this->view($user, $t);
    }

    public function manage(User $user, SupportTicket $t): bool
    {
        return $user->isStaff();
    }
}
