<?php

declare(strict_types=1);

use Flexpik\FilamentStudio\Flows\Enums\FlowRunStatus;
use Flexpik\FilamentStudio\Flows\Enums\FlowRunStepStatus;
use Flexpik\FilamentStudio\Flows\Filament\Resources\FlowResource\Pages\ViewFlowRun;
use Flexpik\FilamentStudio\Flows\Models\StudioFlow;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowRun;
use Flexpik\FilamentStudio\Flows\Models\StudioFlowRunStep;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('renders the run viewer without depending on host Tailwind utilities', function () {
    Role::firstOrCreate(['name' => 'studio-admin', 'guard_name' => 'web']);
    $admin = $this->makeUserWith(['view_flows']);
    $admin->assignRole('studio-admin');
    $this->actingAs($admin);

    $flow = StudioFlow::factory()->create();
    $run = StudioFlowRun::factory()->for($flow, 'flow')->create(['status' => FlowRunStatus::Failed]);

    StudioFlowRunStep::factory()->for($run, 'run')->create([
        'operation_key' => 'op_fail',
        'status' => FlowRunStepStatus::Failed,
        'error_class' => 'RuntimeException',
        'error_message' => 'Something went wrong',
        'error_trace' => '#0 trace',
    ]);

    $component = Livewire::test(ViewFlowRun::class, ['record' => $flow->id, 'runId' => $run->id]);

    foreach (['space-y-4', 'grid-cols-2', 'divide-y', 'text-red-600', 'bg-red-50', 'dark:bg-gray-900', 'font-semibold', 'justify-between'] as $utility) {
        $component->assertDontSeeHtml($utility);
    }

    $component
        ->assertSeeHtml('fi-badge')
        ->assertSeeHtml('var(--danger-')
        ->assertSeeHtml('color-mix(')
        ->assertSee('Something went wrong');
});
