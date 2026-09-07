<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesktopWindowTest extends TestCase
{
    use RefreshDatabase;

    public function test_window_control_endpoints_respond_successfully(): void
    {
        $resMin = $this->postJson('/api/window/minimize');
        $resMin->assertStatus(200)
               ->assertJson(['success' => true, 'action' => 'minimize']);

        $resMax = $this->postJson('/api/window/maximize');
        $resMax->assertStatus(200)
               ->assertJson(['success' => true, 'action' => 'maximize']);

        $resClose = $this->postJson('/api/window/close');
        $resClose->assertStatus(200)
                 ->assertJson(['success' => true, 'action' => 'close']);

        $resReload = $this->postJson('/api/window/reload');
        $resReload->assertStatus(200)
                  ->assertJson(['success' => true, 'action' => 'reload']);

        $resSnap = $this->postJson('/api/window/snap', ['mode' => 'compact']);
        $resSnap->assertStatus(200)
                ->assertJson(['success' => true, 'mode' => 'compact']);

        $resStatus = $this->getJson('/api/window/status');
        $resStatus->assertStatus(200);
    }

    public function test_desktop_titlebar_rendered_in_assistant_view(): void
    {
        $response = $this->get('/assistant');

        $response->assertStatus(200);
        $response->assertSee('id="desktop-titlebar"', false);
        $response->assertSee('win-close-btn', false);
        $response->assertSee('win-snap-menu', false);
        $response->assertSee('File', false);
        $response->assertSee('Edit', false);
        $response->assertSee('View', false);
        $response->assertSee('Window', false);
        $response->assertSee('Help', false);
    }
}
