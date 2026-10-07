<?php

namespace App\Concerns;

use App\Support\PerPage;

trait WithPerPage
{
    public int $perPage = PerPage::DEFAULT;

    public function updatedPerPage(): void
    {
        $this->perPage = PerPage::resolve($this->perPage);
        $this->resetPage();
    }
}
