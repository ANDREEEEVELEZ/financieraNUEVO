<?php

namespace Tests\Feature\Security;

use Tests\TestCase;

/**
 * PR-2c | Task 4.1 – Filament panel IDs must be unique.
 * Both panels must have distinct IDs: 'admin' and 'dashboard'.
 */
class PanelConfigTest extends TestCase
{
    public function test_panel_ids_are_unique(): void
    {
        $panels  = \Filament\Facades\Filament::getPanels();
        $ids     = array_keys($panels);

        $this->assertCount(
            count(array_unique($ids)),
            $ids,
            'Filament panel IDs must be unique — two panels share the same ID'
        );
    }

    public function test_admin_panel_exists_with_correct_id(): void
    {
        $panels = \Filament\Facades\Filament::getPanels();

        $this->assertArrayHasKey('admin', $panels, 'AdminPanelProvider must register with id("admin")');
    }

    public function test_dashboard_panel_exists_with_correct_id(): void
    {
        $panels = \Filament\Facades\Filament::getPanels();

        $this->assertArrayHasKey('dashboard', $panels, 'DashboardPanelProvider must register with id("dashboard")');
    }
}
