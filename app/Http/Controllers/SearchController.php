<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Unit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    /**
     * Global search across clients, bookings, units, payments.
     * Returns categorized matches as JSON for the topbar palette.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => []]);
        }

        $user = $request->user();
        $like = '%' . $q . '%';

        $groups = [];

        // ============ Clients ============
        if ($user?->can('clients.view')) {
            $clients = Client::query()
                ->where(function ($w) use ($like, $q) {
                    $w->where('full_name', 'ilike', $like)
                      ->orWhere('code', 'ilike', $like)
                      ->orWhere('primary_phone', 'ilike', $like)
                      ->orWhere('alt_phone', 'ilike', $like)
                      ->orWhere('email', 'ilike', $like);
                    // CNIC search if 13 digits supplied
                    $cleanCnic = preg_replace('/\D/', '', $q);
                    if (strlen($cleanCnic) === 13) {
                        $w->orWhere('cnic_hash', hash_hmac('sha256', $cleanCnic, config('app.key')));
                    }
                })
                ->limit(6)
                ->get(['id', 'code', 'full_name', 'primary_phone', 'email']);

            if ($clients->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Clients',
                    'icon' => 'users',
                    'items' => $clients->map(fn ($c) => [
                        'id' => "c{$c->id}",
                        'title' => $c->full_name,
                        'subtitle' => $c->code . ($c->primary_phone ? ' · ' . $c->primary_phone : ''),
                        'url' => "/clients/{$c->id}",
                    ])->values(),
                ];
            }
        }

        // ============ Bookings ============
        if ($user?->can('bookings.view')) {
            $bookings = Booking::query()
                ->with(['client:id,full_name', 'unit:id,code'])
                ->where(function ($w) use ($like) {
                    $w->where('code', 'ilike', $like)
                      ->orWhereHas('client', fn ($c) => $c->where('full_name', 'ilike', $like)->orWhere('code', 'ilike', $like))
                      ->orWhereHas('unit', fn ($u) => $u->where('code', 'ilike', $like));
                })
                ->limit(6)
                ->get(['id', 'code', 'status', 'client_id', 'unit_id', 'total_price_minor']);

            if ($bookings->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Bookings',
                    'icon' => 'document',
                    'items' => $bookings->map(fn ($b) => [
                        'id' => "b{$b->id}",
                        'title' => $b->code,
                        'subtitle' => ($b->client?->full_name ?? '—') . ' · ' . ($b->unit?->code ?? '—') . ' · ' . $b->status,
                        'url' => "/bookings/{$b->id}",
                    ])->values(),
                ];
            }
        }

        // ============ Units ============
        if ($user?->can('inventory.view')) {
            $units = Unit::query()
                ->with('block:id,code', 'project:id,name', 'category:id,name')
                ->where(function ($w) use ($like) {
                    $w->where('code', 'ilike', $like)
                      ->orWhere('name', 'ilike', $like);
                })
                ->limit(6)
                ->get(['id', 'code', 'name', 'status', 'project_id', 'block_id', 'unit_category_id']);

            if ($units->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Units',
                    'icon' => 'building',
                    'items' => $units->map(fn ($u) => [
                        'id' => "u{$u->id}",
                        'title' => $u->code,
                        'subtitle' => ($u->project?->name ?? '—') . ' · ' . ($u->category?->name ?? '—') . ' · ' . ($u->status?->value ?? $u->status),
                        'url' => "/inventory/units/{$u->id}",
                    ])->values(),
                ];
            }
        }

        // ============ Payments ============
        if ($user?->can('payments.view')) {
            $payments = Payment::query()
                ->with(['client:id,full_name', 'booking:id,code'])
                ->where(function ($w) use ($like) {
                    $w->where('code', 'ilike', $like)
                      ->orWhere('reference', 'ilike', $like);
                })
                ->limit(6)
                ->get(['id', 'code', 'reference', 'status', 'received_at', 'amount_minor', 'pkr_amount_minor', 'client_id', 'booking_id', 'channel']);

            if ($payments->isNotEmpty()) {
                $groups[] = [
                    'label' => 'Payments',
                    'icon' => 'banknotes',
                    'items' => $payments->map(fn ($p) => [
                        'id' => "p{$p->id}",
                        'title' => $p->code . ($p->reference ? ' · ' . $p->reference : ''),
                        'subtitle' => ($p->booking?->code ?? '—') . ' · ' . ($p->received_at?->format('Y-m-d') ?? '—') . ' · ' . $p->status,
                        'url' => "/payments/{$p->id}",
                    ])->values(),
                ];
            }
        }

        return response()->json(['groups' => $groups]);
    }
}
