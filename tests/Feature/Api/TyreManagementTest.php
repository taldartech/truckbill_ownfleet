<?php

namespace Tests\Feature\Api;

use App\Enums\TyreDamageType;
use App\Enums\TyrePosition;
use App\Enums\TyreStatus;
use App\Support\TyreLayoutDefaults;
use App\Tenant;
use App\Tyre;
use App\TyreAuditLog;
use App\TyreDamage;
use App\TyreRotation;
use App\User;
use App\UserRole;
use App\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TyreManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedRolesAndPermissions();
    }

    public function test_admin_can_create_and_install_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-001',
                'purchase_cost' => 25000,
            ]));

        $create->assertOk();
        $create->assertJsonPath('data.serial_number', 'TYRE-001');
        $create->assertJsonPath('data.status', TyreStatus::InStock->value);

        $tyreId = $create->json('data.id');

        $install = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-02-01',
                'km_at_installation' => 1000,
            ]);

        $install->assertCreated();
        $install->assertJsonPath('data.position', TyrePosition::FrontLeft->value);

        $this->assertDatabaseHas('tyres', [
            'id' => $tyreId,
            'status' => TyreStatus::Installed->value,
        ]);

        $installLog = TyreAuditLog::query()
            ->where('tyre_id', $tyreId)
            ->where('event_type', 'tyre.installed')
            ->firstOrFail();
        $this->assertSame($vehicle->id, $installLog->vehicle_id);
        $this->assertSame(TyrePosition::FrontLeft->value, $installLog->payload['position_code']);
        $this->assertTrue($installLog->tyre->is(Tyre::query()->find($tyreId)));
        $this->assertTrue($installLog->vehicle->is($vehicle));
        $this->assertTrue(Tyre::query()->find($tyreId)->auditLogs->contains($installLog));
    }

    public function test_tyre_install_date_cannot_be_before_purchase_date(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-INVALID-INSTALL',
                'purchase_date' => '2026-05-01',
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-04-01',
                'km_at_installation' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['installed_at']);
    }

    public function test_tyre_damage_date_cannot_be_before_purchase_date(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-INVALID-DAMAGE',
                'purchase_date' => '2026-05-01',
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/damage", [
                'damage_type' => TyreDamageType::Puncture->value,
                'odometer_km' => 1000,
                'occurred_at' => '2026-04-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['occurred_at']);
    }

    public function test_create_tyre_without_purchase_cost_defaults_to_zero(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $response = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-NO-COST',
                'purchase_cost' => null,
            ]));

        $response->assertOk();
        $response->assertJsonPath('data.purchase_cost', 0);

        $this->assertDatabaseHas('tyres', [
            'serial_number' => 'TYRE-NO-COST',
            'purchase_cost' => 0,
        ]);
    }

    public function test_tyre_life_report_returns_data_for_tenant(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        $this->actingAs($user);

        Tyre::query()->create(array_merge($this->operationalAttributesForTenant($tenant), [
            'serial_number' => 'TYRE-LIFE-1',
            'brand' => 'CEAT',
            'purchase_cost' => 10000,
            'purchase_date' => '2026-03-01',
            'status' => TyreStatus::InStock,
        ]));

        $response = $this->tyreApi($tenant)
            ->getJson('/api/reports/tyres/life?from=2026-01-01&to=2026-03-31');

        $response->assertOk();
        $response->assertJsonFragment(['serial_number' => 'TYRE-LIFE-1']);
    }

    public function test_tyre_report_export_returns_excel_for_selected_filters(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        $this->actingAs($user);

        Tyre::query()->create(array_merge($this->operationalAttributesForTenant($tenant), [
            'serial_number' => 'TYRE-EXPORT-1',
            'brand' => 'CEAT',
            'purchase_cost' => 10000,
            'purchase_date' => '2026-03-01',
            'status' => TyreStatus::InStock,
        ]));

        $response = $this->tyreApi($tenant)
            ->get('/api/reports/tyres/export?type=life&from=2026-01-01&to=2026-03-31');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );
        $this->assertStringContainsString('.xlsx', (string) $response->headers->get('content-disposition'));
    }

    public function test_vehicle_tyre_positions_returns_all_slots(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $response = $this->tyreApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-positions");

        $response->assertOk();
        $response->assertOk();
        $response->assertJsonCount(7, 'data');
        $response->assertJsonPath('data.0.position_label', 'Front Left');
    }

    public function test_vehicle_tyre_positions_respects_tyre_layout_with_multiple_spares(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        $vehicle->update(['tyre_layout' => 'TYRES_12']);
        Sanctum::actingAs($user);

        $response = $this->tyreApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-positions");

        $response->assertOk();
        $response->assertJsonCount(12, 'data');
        $response->assertJsonFragment(['position' => 'SPARE_1', 'position_label' => 'Spare 1']);
        $response->assertJsonFragment(['position' => 'SPARE_2', 'position_label' => 'Spare 2']);
        $response->assertJsonFragment(['position' => 'AX2_L_OUT', 'position_label' => 'Axle 2 — Left (Outer)']);
    }

    public function test_vehicle_tyre_history_returns_summary_and_installation_log(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $activeTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-ACTIVE', TyrePosition::FrontLeft->value);

        $removedTyreCreate = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-REMOVED',
                'purchase_cost' => 20000,
            ]));
        $removedTyreId = $removedTyreCreate->json('data.id');

        $removedInstall = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$removedTyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontRight->value,
                'installed_at' => '2026-01-15',
                'km_at_installation' => 1000,
            ]);
        $removedInstallationId = $removedInstall->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$removedInstallationId}/remove", [
                'removed_at' => '2026-03-01',
                'stint_km' => 2500,
            ])
            ->assertOk();

        $response = $this->tyreApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-history");

        $response->assertOk();
        $response->assertJsonPath('summary.unique_tyres_used', 2);
        $response->assertJsonPath('summary.total_stints', 2);
        $response->assertJsonPath('summary.currently_installed', 1);
        $response->assertJsonPath('summary.total_km_on_truck', 6500);
        $response->assertJsonCount(2, 'history');
        $response->assertJsonFragment([
            'tyre_id' => $activeTyreId,
            'tyre_serial_number' => 'TYRE-ACTIVE',
            'is_active' => true,
            'stint_status' => 'INSTALLED',
        ]);
        $response->assertJsonFragment([
            'tyre_id' => $removedTyreId,
            'tyre_serial_number' => 'TYRE-REMOVED',
            'is_active' => false,
            'stint_km' => 2500,
            'stint_status' => 'IN_STOCK',
        ]);
    }

    public function test_vehicle_tyre_history_shows_scrapped_only_on_last_stint(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-SCRAP-HIST', TyrePosition::FrontLeft->value);

        $firstInstall = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}")
            ->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$firstInstall}/remove", [
                'removed_at' => '2026-01-15',
                'stint_km' => 1000,
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontRight->value,
                'installed_at' => '2026-02-01',
                'km_at_installation' => 5000,
            ])
            ->assertCreated();

        $secondInstall = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}")
            ->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$secondInstall}/remove", [
                'removed_at' => '2026-03-01',
                'stint_km' => 1500,
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/scrap", [
                'scrapped_at' => '2026-03-02',
            ])
            ->assertOk();

        $response = $this->tyreApi($tenant)
            ->getJson("/api/vehicles/{$vehicle->id}/tyre-history");

        $response->assertOk();
        $response->assertJsonFragment([
            'tyre_serial_number' => 'TYRE-SCRAP-HIST',
            'installed_at' => '2026-02-01',
            'stint_status' => 'SCRAPPED',
        ]);
        $response->assertJsonFragment([
            'tyre_serial_number' => 'TYRE-SCRAP-HIST',
            'installed_at' => '2026-03-01',
            'stint_status' => 'IN_STOCK',
        ]);

        $scrapLog = TyreAuditLog::query()
            ->where('tyre_id', $tyreId)
            ->where('event_type', 'tyre.scrapped')
            ->firstOrFail();
        $this->assertSame('2026-03-02', $scrapLog->payload['scrapped_at']);
        $this->assertTrue($scrapLog->tyre->is(Tyre::query()->find($tyreId)));
        $this->assertTrue(Tyre::query()->find($tyreId)->auditLogs->contains($scrapLog));
    }

    public function test_remove_rejects_unrealistic_same_day_stint(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-SAME-DAY', TyrePosition::FrontLeft->value);
        $installationId = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}")
            ->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-03-01',
                'stint_km' => 20000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['odometer_km']);
    }

    public function test_tyre_timeline_returns_newest_events_first_in_logical_order(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicleB = Vehicle::query()->create(array_merge($this->operationalAttributesForTenant($tenant), [
            'registration_number' => 'MH12TY9997',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 15000,
        ]));

        $tyreId = $this->createAndInstallTyre($tenant, $vehicleA, 'TYRE-TIMELINE', TyrePosition::FrontLeft->value);

        $install = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");
        $installationId = $install->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-10',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 200,
                'stint_km' => 1000,
            ])
            ->assertCreated();

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-11',
                'stint_km' => 1500,
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-12',
                'km_at_installation' => 15000,
            ])
            ->assertCreated();

        $response = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $response->assertOk();
        $timeline = $response->json('timeline');
        $this->assertNotEmpty($timeline);

        for ($index = 0; $index < count($timeline) - 1; $index++) {
            $this->assertGreaterThanOrEqual(
                $timeline[$index + 1]['sort_at'],
                $timeline[$index]['sort_at'],
            );
        }

        $chronological = array_reverse($timeline);
        $installCount = 0;
        $seenRemoveBetweenInstalls = true;

        foreach ($chronological as $event) {
            if ($event['type'] === 'installed') {
                if ($installCount > 0 && ! $seenRemoveBetweenInstalls) {
                    $this->fail('Second install appeared before a remove event.');
                }
                $installCount++;
                $seenRemoveBetweenInstalls = false;
            }

            if ($event['type'] === 'removed') {
                $seenRemoveBetweenInstalls = true;
            }
        }

        $this->assertGreaterThanOrEqual(2, $installCount);
    }

    public function test_tyre_timeline_same_day_remove_sorts_after_install_and_omits_midnight_time(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $today = now()->toDateString();

        $tyreId = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-SAME-DAY',
                'purchase_cost' => 20000,
                'purchase_date' => $today,
            ]))
            ->assertOk()
            ->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => $today,
                'km_at_installation' => 0,
            ])
            ->assertCreated();

        $installationId = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}")
            ->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => $today,
                'odometer_km' => 0,
            ])
            ->assertOk();

        $timeline = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}")
            ->assertOk()
            ->json('timeline');

        $installationEvents = array_values(array_filter(
            $timeline,
            fn(array $event) => in_array($event['type'], ['installed', 'removed'], true),
        ));

        $this->assertCount(2, $installationEvents);
        $this->assertSame('removed', $installationEvents[0]['type']);
        $this->assertSame('installed', $installationEvents[1]['type']);
        $this->assertGreaterThanOrEqual($installationEvents[1]['sort_at'], $installationEvents[0]['sort_at']);
        $this->assertNull($installationEvents[0]['time']);
        $this->assertNull($installationEvents[1]['time']);
    }

    public function test_tyre_timeline_replace_detail_omits_duplicate_remaining_life_text(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $oldTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-OLD-TIMELINE', TyrePosition::FrontLeft->value);

        $newTyreId = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-NEW-TIMELINE',
            ]))
            ->assertOk()
            ->json('data.id');

        $this->tyreApi($tenant)
            ->postJson('/api/tyres/replace', [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'old_tyre_id' => $oldTyreId,
                'new_tyre_id' => $newTyreId,
                'replaced_at' => '2026-07-09',
                'odometer_km' => 1000,
            ])
            ->assertCreated();

        $timeline = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$oldTyreId}")
            ->assertOk()
            ->json('timeline');

        $replaceEvent = collect($timeline)->firstWhere('type', 'replacement');
        $this->assertNotNull($replaceEvent);
        $this->assertStringContainsString('Ran 0.00 km', $replaceEvent['detail']);
        $this->assertStringNotContainsString('~-', $replaceEvent['detail']);
        $this->assertStringNotContainsString('km life left', $replaceEvent['detail']);
        $this->assertStringContainsString('may still be usable', $replaceEvent['detail']);
    }

    public function test_tyre_timeline_keeps_install_on_other_vehicle_when_replacement_same_day(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicleB = Vehicle::query()->create(array_merge($this->operationalAttributesForTenant($tenant), [
            'registration_number' => 'MH12TY8881',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 12000,
        ]));

        $oldTyreId = $this->createAndInstallTyre($tenant, $vehicleA, 'TYRE-MULTI-TRUCK', TyrePosition::FrontLeft->value);

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$this->activeInstallationId($oldTyreId)}/remove", [
                'removed_at' => '2026-07-09',
                'odometer_km' => 2000,
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$oldTyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-07-09',
                'km_at_installation' => 12000,
            ])
            ->assertCreated();

        $newTyreId = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-REPL-NEW',
            ]))
            ->assertOk()
            ->json('data.id');

        $this->tyreApi($tenant)
            ->postJson('/api/tyres/replace', [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'old_tyre_id' => $oldTyreId,
                'new_tyre_id' => $newTyreId,
                'replaced_at' => '2026-07-09',
                'odometer_km' => 12000,
            ])
            ->assertCreated();

        $timeline = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$oldTyreId}")
            ->assertOk()
            ->json('timeline');

        $installEvents = array_values(array_filter(
            $timeline,
            fn(array $event) => $event['type'] === 'installed',
        ));

        $this->assertCount(1, $installEvents);
        $this->assertStringContainsString($vehicleA->registration_number, $installEvents[0]['title']);
        $this->assertStringContainsString('2026-03-01 → 2026-07-09', $installEvents[0]['detail']);
    }

    public function test_rotate_swaps_tyres_when_target_position_is_occupied(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $leftTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-LEFT', TyrePosition::FrontLeft->value);
        $rightTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-RIGHT', TyrePosition::FrontRight->value);

        $response = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$leftTyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 500,
                'stint_km' => 8000,
                'reason' => 'Steer swap',
            ]);

        $response->assertCreated();
        $response->assertJsonPath('data.new_position', TyrePosition::FrontRight->value);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $leftTyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => TyrePosition::FrontRight->value,
            'removed_at' => null,
        ]);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $rightTyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => TyrePosition::FrontLeft->value,
            'removed_at' => null,
        ]);

        $this->assertDatabaseHas('tyre_rotations', [
            'tyre_id' => $leftTyreId,
            'previous_position' => TyrePosition::FrontLeft->value,
            'new_position' => TyrePosition::FrontRight->value,
            'cost' => 500,
        ]);

        $this->assertDatabaseHas('tyre_expenses', [
            'tyre_id' => $leftTyreId,
            'expense_type' => 'ROTATION',
            'amount' => 500,
        ]);

        $this->assertDatabaseHas('tyre_rotations', [
            'tyre_id' => $rightTyreId,
            'previous_position' => TyrePosition::FrontRight->value,
            'new_position' => TyrePosition::FrontLeft->value,
        ]);

        $leftRotation = TyreRotation::query()->where('tyre_id', $leftTyreId)->firstOrFail();
        $this->assertEquals($leftTyreId, $leftRotation->tyre->id);
        $this->assertEquals('TYRE-LEFT', $leftRotation->tyre->serial_number);
        $this->assertEquals($vehicle->id, $leftRotation->vehicle->id);

        $rightRotation = TyreRotation::query()->where('tyre_id', $rightTyreId)->firstOrFail();
        $this->assertEquals($rightTyreId, $rightRotation->tyre->id);
        $this->assertEquals('TYRE-RIGHT', $rightRotation->tyre->serial_number);
        $this->assertEquals($vehicle->id, $rightRotation->vehicle->id);
    }

    public function test_rotate_moves_tyre_to_unoccupied_position_without_swap(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-SOLO', TyrePosition::FrontLeft->value);

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 300,
                'stint_km' => 5000,
                'reason' => 'Routine rotation to empty slot',
            ])
            ->assertCreated()
            ->assertJsonPath('data.new_position', TyrePosition::FrontRight->value);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $tyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => TyrePosition::FrontRight->value,
            'removed_at' => null,
        ]);

        $this->assertDatabaseCount('tyre_rotations', 1);

        $rotation = TyreRotation::query()->where('tyre_id', $tyreId)->firstOrFail();
        $this->assertEquals($tyreId, $rotation->tyre->id);
        $this->assertEquals($vehicle->id, $rotation->vehicle->id);

        $rotateLog = TyreAuditLog::query()
            ->where('tyre_id', $tyreId)
            ->where('event_type', 'tyre.rotated')
            ->firstOrFail();
        $this->assertSame($vehicle->id, $rotateLog->vehicle_id);
        $this->assertSame(TyrePosition::FrontLeft->value, $rotateLog->payload['previous_position']);
        $this->assertSame(TyrePosition::FrontRight->value, $rotateLog->payload['new_position']);
        $this->assertTrue($rotateLog->tyre->is(Tyre::query()->find($tyreId)));
        $this->assertTrue($rotateLog->vehicle->is($vehicle));
        $this->assertTrue(Tyre::query()->find($tyreId)->auditLogs->contains($rotateLog));
    }

    public function test_cannot_rotate_uninstalled_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-STOCK',
            ]));
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 500,
                'stint_km' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['new_position']);
    }

    public function test_cannot_rotate_scrapped_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-SCRAPPED',
            ]));
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/scrap", [
                'scrapped_at' => '2026-06-01',
                'scrap_notes' => 'Damaged beyond repair',
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontLeft->value,
                'new_position' => TyrePosition::FrontRight->value,
                'cost' => 500,
                'stint_km' => 1000,
            ])
            ->assertUnprocessable();
    }

    public function test_cannot_rotate_when_previous_position_does_not_match_current_position(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-MISMATCH', TyrePosition::FrontLeft->value);

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/rotate", [
                'rotation_date' => '2026-06-05',
                'previous_position' => TyrePosition::FrontRight->value,
                'new_position' => TyrePosition::RearLeftInner->value,
                'cost' => 500,
                'stint_km' => 2000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['previous_position'])
            ->assertJsonPath('errors.previous_position.0', 'Does not match current tyre position.');
    }

    public function test_cannot_install_already_installed_tyre_on_another_vehicle(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $operational = $this->operationalAttributesForTenant($tenant);
        $vehicleB = Vehicle::query()->create(array_merge($operational, [
            'registration_number' => 'MH12TY0002',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 8000,
        ]));

        $tyreId = $this->createAndInstallTyre($tenant, $vehicleA, 'TYRE-MOUNTED', TyrePosition::FrontLeft->value);

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tyre'])
            ->assertJsonPath('errors.tyre.0', 'Only in-stock tyres can be installed.');
    }

    public function test_cannot_install_scrapped_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-SCRAP-NO-INSTALL',
            ]));
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/scrap", [
                'scrapped_at' => '2026-06-01',
                'scrap_notes' => 'Total tyre failure',
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tyre'])
            ->assertJsonPath('errors.tyre.0', 'Scrapped tyres cannot be installed.');
    }

    public function test_early_removal_shows_recommendation_when_life_remaining(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-EARLY',
                'purchase_cost' => 20000,
            ]));

        $tyreId = $create->json('data.id');

        $install = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
                'km_at_installation' => 1000,
            ]);

        $installationId = $install->json('data.id');

        $preview = $this->tyreApi($tenant)
            ->getJson("/api/tyre-installations/{$installationId}/removal-preview?stint_km=1000");

        $preview->assertOk();
        $preview->assertJsonPath('data.level', 'warning');
        $this->assertStringContainsString('1000', $preview->json('data.message'));
    }

    public function test_odometer_auto_calculates_stint_on_install_remove_and_transfer(): void
    {
        [$tenant, $user, $vehicleA] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicleB = Vehicle::query()->create(array_merge($this->operationalAttributesForTenant($tenant), [
            'registration_number' => 'MH12TY9998',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 10000,
        ]));

        $vehicleA->update(['odometer_km' => 5000]);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-ODO',
                'current_km' => 0,
            ]));

        $tyreId = $create->json('data.id');

        $install = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleA->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ]);

        $installationId = $install->json('data.id');
        $install->assertJsonPath('data.km_at_installation', 5000);

        $this->tyreApi($tenant)
            ->patchJson("/api/tyre-installations/{$installationId}/stint-km", [
                'odometer_km' => 6000,
            ])
            ->assertOk()
            ->assertJsonPath('data.stint_km', 1000);

        $show = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.active_stint_km', 1000);
        $show->assertJsonPath('life_summary.life_used_percent', 2.5);

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-01',
                'odometer_km' => 6000,
            ])
            ->assertOk()
            ->assertJsonPath('data.running_km', 1000)
            ->assertJsonPath('data.km_at_removal', 6000);

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicleB->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-06-02',
                'km_at_installation' => 10000,
            ])
            ->assertCreated();

        $afterTransfer = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $afterTransfer->assertJsonPath('life_summary.stock_km', 1000);
        $afterTransfer->assertJsonPath('life_summary.life_used_percent', 2.5);

        $newInstallationId = $afterTransfer->json('data.active_installation.id');

        $this->tyreApi($tenant)
            ->patchJson("/api/tyre-installations/{$newInstallationId}/stint-km", [
                'odometer_km' => 12000,
            ])
            ->assertOk()
            ->assertJsonPath('data.stint_km', 2000);

        $final = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $final->assertJsonPath('life_summary.total_km_used', 3000);
        $final->assertJsonPath('life_summary.remaining_life_km', 37000);
        $final->assertJsonPath('life_summary.life_used_percent', 7.5);
    }

    public function test_manual_stint_km_updates_life_without_vehicle_odometer(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-MANUAL',
                'current_km' => 0,
            ]));

        $tyreId = $create->json('data.id');

        $install = $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ]);

        $installationId = $install->json('data.id');

        $this->tyreApi($tenant)
            ->patchJson("/api/tyre-installations/{$installationId}/stint-km", [
                'stint_km' => 8000,
            ])
            ->assertOk();

        $show = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertOk();
        $show->assertJsonPath('life_summary.life_used_percent', 20);
        $show->assertJsonPath('life_summary.active_stint_km', 8000);

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$installationId}/remove", [
                'removed_at' => '2026-06-01',
                'stint_km' => 12000,
            ])
            ->assertOk();

        $afterRemove = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $afterRemove->assertJsonPath('life_summary.total_km_used', 12000);
        $afterRemove->assertJsonPath('life_summary.life_used_percent', 30);
    }

    public function test_tyre_index_can_filter_by_truck(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $onTruckId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-ON-TRUCK', TyrePosition::FrontLeft->value);

        $otherVehicle = Vehicle::query()->create([
            'tenant_id' => $tenant->id,
            'registration_number' => 'MH12TY9999',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 1000,
        ]);

        $this->createAndInstallTyre($tenant, $otherVehicle, 'TYRE-OTHER-TRUCK', TyrePosition::FrontLeft->value);

        Tyre::query()->create([
            'tenant_id' => $tenant->id,
            'serial_number' => 'TYRE-STOCK',
            'purchase_cost' => 10000,
            'status' => TyreStatus::InStock,
        ]);

        $response = $this->tyreApi($tenant)
            ->getJson("/api/tyres?vehicle_id={$vehicle->id}");

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $onTruckId);
        $response->assertJsonPath('data.0.serial_number', 'TYRE-ON-TRUCK');
    }

    public function test_admin_can_correct_km_already_used_on_edit(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-FIX-KM',
                'current_km' => 10000,
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->putJson("/api/tyres/{$tyreId}", [
                'current_km' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('data.current_km', 0);

        $show = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.stock_km', 0);
        $show->assertJsonPath('life_summary.life_used_percent', 0);
    }

    public function test_used_tyre_shows_prior_life_at_install(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-OLD',
                'current_km' => 18000,
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => TyrePosition::FrontLeft->value,
                'installed_at' => '2026-03-01',
            ])
            ->assertCreated();

        $show = $this->tyreApi($tenant)
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.stock_km', 18000);
        $show->assertJsonPath('life_summary.life_used_percent', 45);
    }

    public function test_recapped_damage_updates_expected_life_km(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        $operational = $this->operationalAttributesForTenant($tenant);
        $user->update(['branch_id' => $operational['branch_id']]);
        Sanctum::actingAs($user->fresh());

        $create = $this->tyreApi($tenant)
            ->withHeader('X-Branch-Id', (string) $operational['branch_id'])
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-RECAP',
                'expected_life_km' => 40000,
            ]));

        $create->assertOk();
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->withHeader('X-Branch-Id', (string) $operational['branch_id'])
            ->postJson("/api/tyres/{$tyreId}/damage", [
                'damage_type' => 'RECAPPED',
                'occurred_at' => '2026-07-04',
                'odometer_km' => 0,
                'repair_cost' => 3500,
                'expected_life_km' => 55000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tyres', [
            'id' => $tyreId,
            'expected_life_km' => 55000,
        ]);

        $damage = TyreDamage::query()->where('tyre_id', $tyreId)->firstOrFail();
        $this->assertEquals($tyreId, $damage->tyre->id);
        $this->assertEquals('TYRE-RECAP', $damage->tyre->serial_number);

        $show = $this->tyreApi($tenant)
            ->withHeader('X-Branch-Id', (string) $operational['branch_id'])
            ->getJson("/api/tyres/{$tyreId}");

        $show->assertJsonPath('life_summary.expected_life_km', 55000);
    }

    public function test_recapped_damage_requires_expected_life_km(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        $operational = $this->operationalAttributesForTenant($tenant);
        $user->update(['branch_id' => $operational['branch_id']]);
        Sanctum::actingAs($user->fresh());

        $create = $this->tyreApi($tenant)
            ->withHeader('X-Branch-Id', (string) $operational['branch_id'])
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-RECAP-VAL',
            ]));

        $create->assertOk();
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->withHeader('X-Branch-Id', (string) $operational['branch_id'])
            ->postJson("/api/tyres/{$tyreId}/damage", [
                'damage_type' => 'RECAPPED',
                'occurred_at' => '2026-07-04',
                'odometer_km' => 0,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['expected_life_km']);
    }

    public function test_record_damage_on_installed_tyre_links_vehicle_and_records_expense(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-PUNCTURE', TyrePosition::FrontLeft->value);

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/damage", [
                'damage_type' => TyreDamageType::Puncture->value,
                'occurred_at' => now()->toDateString(),
                'odometer_km' => 6000,
                'repair_cost' => 450,
                'description' => 'Highway puncture patched',
                'is_repaired' => true,
            ])
            ->assertCreated()
            ->assertJsonPath('data.position', TyrePosition::FrontLeft->value)
            ->assertJsonPath('data.repair_cost', 450)
            ->assertJsonPath('data.vehicle_registration', $vehicle->registration_number);

        $damage = TyreDamage::query()->where('tyre_id', $tyreId)->firstOrFail();
        $this->assertEquals($tyreId, $damage->tyre->id);
        $this->assertEquals($vehicle->id, $damage->vehicle?->id);
        $this->assertEquals(TyrePosition::FrontLeft->value, $damage->position_code);

        $this->assertDatabaseHas('tyre_expenses', [
            'tyre_id' => $tyreId,
            'expense_type' => 'REPAIR',
            'amount' => 450,
        ]);
    }

    public function test_cannot_record_damage_on_scrapped_tyre(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-SCRAP-DMG',
            ]));
        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/scrap", [
                'scrapped_at' => now()->subDay()->toDateString(),
                'scrap_notes' => 'Decommissioned',
            ])
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/damage", [
                'damage_type' => TyreDamageType::Puncture->value,
                'occurred_at' => now()->toDateString(),
                'odometer_km' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tyre'])
            ->assertJsonPath('errors.tyre.0', 'Cannot record repair on a scrapped tyre.');
    }

    public function test_moderator_can_view_tyres_but_not_manage(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('moderator'), 0, 2);
        Sanctum::actingAs($user);

        $this->tyreApi($tenant)
            ->getJson('/api/tyres')
            ->assertOk();

        $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-MOD',
            ]))
            ->assertForbidden();
    }

    public function test_install_syncs_vehicle_odometer_when_higher_than_stored_reading(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $vehicle->update(['odometer_km' => 50000]);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-ODO-SYNC',
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => 'FRONT_LEFT',
                'installed_at' => '2026-07-01',
                'km_at_installation' => 52000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('vehicles', [
            'id' => $vehicle->id,
            'odometer_km' => 52000,
        ]);
    }

    public function test_cannot_scrap_tyre_while_installed(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-SCRAP-BLOCK', 'FRONT_LEFT');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/scrap", [
                'scrapped_at' => '2026-07-05',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['tyre']);
    }

    public function test_replace_removes_old_tyre_and_installs_new_tyre(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $oldTyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-OLD-REP', 'FRONT_LEFT');

        $newCreate = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-NEW-REP',
            ]));
        $newTyreId = $newCreate->json('data.id');

        $this->tyreApi($tenant)
            ->postJson('/api/tyres/replace', [
                'vehicle_id' => $vehicle->id,
                'position_code' => 'FRONT_LEFT',
                'old_tyre_id' => $oldTyreId,
                'new_tyre_id' => $newTyreId,
                'replaced_at' => '2026-07-02',
                'odometer_km' => 6000,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tyres', [
            'id' => $oldTyreId,
            'status' => TyreStatus::InStock->value,
        ]);

        $this->assertDatabaseHas('tyres', [
            'id' => $newTyreId,
            'status' => TyreStatus::Installed->value,
        ]);

        $this->assertDatabaseHas('tyre_installations', [
            'tyre_id' => $newTyreId,
            'vehicle_id' => $vehicle->id,
            'position_code' => 'FRONT_LEFT',
            'removed_at' => null,
        ]);

        $removeLog = TyreAuditLog::query()
            ->where('tyre_id', $oldTyreId)
            ->where('event_type', 'tyre.removed')
            ->firstOrFail();
        $this->assertTrue($removeLog->payload['replaced']);
        $this->assertTrue($removeLog->tyre->is(Tyre::query()->find($oldTyreId)));
        $this->assertTrue($removeLog->vehicle->is($vehicle));
        $this->assertTrue(Tyre::query()->find($oldTyreId)->auditLogs->contains($removeLog));
    }

    public function test_cost_per_km_report_uses_lifetime_km_without_double_counting(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $purchaseDate = now()->subMonth()->toDateString();
        $tyreId = $this->createAndInstallTyre($tenant, $vehicle, 'TYRE-COST-KM', 'FRONT_LEFT', $purchaseDate);
        $removedAt = now()->subDays(2)->toDateString();

        $this->tyreApi($tenant)
            ->postJson("/api/tyre-installations/{$this->activeInstallationId($tyreId)}/remove", [
                'removed_at' => $removedAt,
                'odometer_km' => 7000,
            ])
            ->assertOk();

        $from = now()->subMonths(2)->toDateString();
        $to = now()->subDay()->toDateString();

        $response = $this->tyreApi($tenant)
            ->getJson("/api/reports/tyres/cost-per-km?from={$from}&to={$to}");

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('serial_number', 'TYRE-COST-KM');
        $this->assertNotNull($row);
        $this->assertSame(6000.0, (float) $row['total_km']);
    }

    public function test_vehicle_odometer_cannot_decrease_via_update(): void
    {
        [$tenant, $user, $vehicle] = $this->createFixtures('admin');
        Sanctum::actingAs($user);

        $this->tyreApi($tenant)
            ->putJson("/api/vehicles/{$vehicle->id}", [
                'odometer_km' => 1000,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['odometer_km']);
    }

    public function test_admin_can_delete_in_stock_tyre(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-DELETE',
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->deleteJson("/api/tyres/{$tyreId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('tyres', ['id' => $tyreId]);
    }

    public function test_tyre_inspection_updates_last_inspection_fields(): void
    {
        [$tenant, $user] = array_slice($this->createFixtures('admin'), 0, 2);
        Sanctum::actingAs($user);

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => 'TYRE-INSP',
            ]));

        $tyreId = $create->json('data.id');

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/inspections", [
                'inspected_at' => '2026-07-05',
                'tread_depth_mm' => 8.5,
                'result' => 'PASS',
                'next_due_at' => '2026-10-05',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('tyres', [
            'id' => $tyreId,
            'last_tread_depth_mm' => 8.5,
        ]);

        $inspLog = TyreAuditLog::query()
            ->where('tyre_id', $tyreId)
            ->where('event_type', 'tyre.inspected')
            ->firstOrFail();
        $this->assertSame('PASS', $inspLog->payload['result']);
        $this->assertEquals(8.5, $inspLog->payload['tread_depth_mm']);
        $this->assertSame('2026-10-05', $inspLog->payload['next_due_at']);
        $this->assertTrue($inspLog->tyre->is(Tyre::query()->find($tyreId)));
        $this->assertTrue(Tyre::query()->find($tyreId)->auditLogs->contains($inspLog));
    }

    private function activeInstallationId(string $tyreId): string
    {
        return (string) \App\TyreInstallation::query()
            ->withoutGlobalScopes()
            ->where('tyre_id', $tyreId)
            ->whereNull('removed_at')
            ->value('id');
    }

    private function tyreApi(Tenant $tenant): static
    {
        $branchId = $this->operationalAttributesForTenant($tenant)['branch_id'];

        return $this->withHeader('X-Tenant-Id', $tenant->id)
            ->withHeader('X-Branch-Id', (string) $branchId);
    }

    private function createAndInstallTyre(Tenant $tenant, Vehicle $vehicle, string $serial, string $positionCode, ?string $purchaseDate = null): string
    {
        $purchaseDate = $purchaseDate ?? '2026-01-15';

        $create = $this->tyreApi($tenant)
            ->postJson('/api/tyres', $this->validTyrePayload([
                'serial_number' => $serial,
                'purchase_cost' => 20000,
                'purchase_date' => $purchaseDate,
            ]));

        $create->assertOk();
        $tyreId = $create->json('data.id');

        $installedAt = $purchaseDate > '2026-03-01' ? $purchaseDate : '2026-03-01';

        $this->tyreApi($tenant)
            ->postJson("/api/tyres/{$tyreId}/install", [
                'vehicle_id' => $vehicle->id,
                'position_code' => $positionCode,
                'installed_at' => $installedAt,
                'km_at_installation' => 1000,
            ])
            ->assertCreated();

        return $tyreId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validTyrePayload(array $overrides = []): array
    {
        return array_merge([
            'serial_number' => 'TYRE-' . fake()->unique()->numerify('####'),
            'brand' => 'MRF',
            'size' => '10.00-20',
            'tyre_type' => 'RADIAL',
            'purchase_date' => '2026-01-15',
            'expected_life_km' => 40000,
        ], $overrides);
    }

    /**
     * @return array{0: Tenant, 1: User, 2: Vehicle}
     */
    private function createFixtures(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Tyre Tenant',
            'slug' => fake()->unique()->slug(),
            'timezone' => 'Asia/Kolkata',
            'is_active' => true,
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'user_role_id' => UserRole::idFor($role) ?? UserRole::ADMIN,
        ]);

        $user = $this->assignBranchIfRequired($user, $tenant);
        $operational = $this->operationalAttributesForTenant($tenant);

        $vehicle = Vehicle::query()->create(array_merge($operational, [
            'registration_number' => 'MH12TY0001',
            'vehicle_type' => 'TRUCK',
            'odometer_km' => 5000,
        ]));

        TyreLayoutDefaults::seedForTenant($tenant->id);

        return [$tenant, $user, $vehicle];
    }
}
