<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Pages\StudioDashboardPage;

it('grants access to a user holding the dashboard permission the policy defines', function () {
    // StudioDashboardPolicy::viewAny() checks ViewAny:StudioDashboard, and that is
    // the permission the package's shield integration creates. The page used to
    // demand View:StudioDashboardPage, a name nothing ever registers, so the
    // Studio Dashboard was unreachable for every user.
    $this->actingAs($this->makeUserWith(['ViewAny:StudioDashboard']));

    expect(StudioDashboardPage::canAccess())->toBeTrue();
});

it('denies access to a user without any studio dashboard permission', function () {
    $this->actingAs($this->makeUserWith(['some.unrelated.permission']));

    expect(StudioDashboardPage::canAccess())->toBeFalse();
});
