<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class BacktestStrategyController extends Controller
{
    private const OFFSETS = [0, 50, 100, 150, 200, 250, 300, 350, 400, 450, 500];

    public function index(Request $request): View
    {
        $strategies = DB::table('backtest_strategies')
                        ->when($request->filled('search'), function ($query) use ($request): void {
                            $search = '%' . $request->string('search')->toString() . '%';
                            $query->where(function ($query) use ($search): void {
                                $query->where('name', 'like', $search)
                                      ->orWhere('slug', 'like', $search);
                            });
                        })
                        ->orderByDesc('is_active')
                        ->orderBy('name')
                        ->paginate(20)
                        ->withQueryString();

        return view('backtest.strategies.index', compact('strategies'));
    }

    public function create(): View
    {
        return view('backtest.strategies.form', [
            'strategy' => null,
            'definition' => ['legs' => []],
            'parameters' => $this->defaultParameters(),
            'offsets' => self::OFFSETS,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $slug = $this->uniqueSlug($data['slug'] ?: $data['name']);

        DB::table('backtest_strategies')->insert([
            'slug' => $slug,
            'name' => $data['name'],
            'version' => 1,
            'definition' => json_encode(['legs' => $data['legs']], JSON_THROW_ON_ERROR),
            'parameters' => json_encode($data['parameters'], JSON_THROW_ON_ERROR),
            'is_active' => $data['is_active'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('backtest.strategies.index')
            ->with('success', 'Strategy created successfully.');
    }

    public function edit(int $id): View
    {
        $strategy = DB::table('backtest_strategies')->find($id);

        abort_unless($strategy, 404);

        return view('backtest.strategies.form', [
            'strategy' => $strategy,
            'definition' => json_decode($strategy->definition ?: '{"legs":[]}', true),
            'parameters' => array_merge(
                $this->defaultParameters(),
                json_decode($strategy->parameters ?: '{}', true),
            ),
            'offsets' => self::OFFSETS,
        ]);
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $strategy = DB::table('backtest_strategies')->find($id);
        abort_unless($strategy, 404);

        $data = $this->validated($request);
        $slug = $this->uniqueSlug($data['slug'] ?: $data['name'], $id);

        DB::table('backtest_strategies')
          ->where('id', $id)
          ->update([
              'slug' => $slug,
              'name' => $data['name'],
              'version' => ((int) $strategy->version) + 1,
              'definition' => json_encode(['legs' => $data['legs']], JSON_THROW_ON_ERROR),
              'parameters' => json_encode($data['parameters'], JSON_THROW_ON_ERROR),
              'is_active' => $data['is_active'],
              'updated_at' => now(),
          ]);

        return redirect()
            ->route('backtest.strategies.index')
            ->with('success', 'Strategy updated successfully.');
    }

    public function clone(int $id): RedirectResponse
    {
        $strategy = DB::table('backtest_strategies')->find($id);
        abort_unless($strategy, 404);

        $newId = DB::table('backtest_strategies')->insertGetId([
            'slug' => $this->uniqueSlug($strategy->slug . '-copy'),
            'name' => $strategy->name . ' Copy',
            'version' => 1,
            'definition' => $strategy->definition,
            'parameters' => $strategy->parameters,
            'is_active' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return redirect()
            ->route('backtest.strategies.edit', $newId)
            ->with('success', 'Strategy cloned. Review it before activating.');
    }

    public function destroy(int $id): RedirectResponse
    {
        DB::table('backtest_strategies')
          ->where('id', $id)
          ->update([
              'is_active' => 0,
              'updated_at' => now(),
          ]);

        return back()->with('success', 'Strategy deactivated.');
    }

    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => ['nullable', 'string', 'max:150'],
            'is_active' => ['nullable', 'boolean'],
            'parameters.entry_time' => ['required', 'date_format:H:i'],
            'parameters.exit_time' => ['required', 'date_format:H:i'],
            'parameters.default_lots' => ['required', 'integer', 'min:1'],
            'parameters.default_side' => ['required', 'in:BUY,SELL'],
            'parameters.snapshot_minutes' => ['required', 'integer', 'min:1'],
            'parameters.entry_price_field' => ['required', 'in:open,high,low,close'],
            'parameters.snapshot_price_field' => ['required', 'in:open,high,low,close'],
            'parameters.missing_data_policy' => ['required', 'in:skip_day,fail_day'],
            'legs' => ['required', 'array', 'min:1'],
            'legs.*.lots' => ['required', 'integer', 'min:1'],
            'legs.*.side' => ['required', 'in:BUY,SELL'],
            'legs.*.moneyness' => ['required', 'in:ATM,ITM,OTM'],
            'legs.*.option_type' => ['required', 'in:CE,PE'],
            'legs.*.strike_offset' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);

        foreach ($validated['legs'] as $leg) {
            if ($leg['moneyness'] === 'ATM' && (int) $leg['strike_offset'] !== 0) {
                abort(422, 'ATM legs must have a strike offset of zero.');
            }
        }

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'] ?? '',
            'is_active' => (bool) ($validated['is_active'] ?? false),
            'legs' => array_values($validated['legs']),
            'parameters' => $validated['parameters'],
        ];
    }

    private function defaultParameters(): array
    {
        return [
            'entry_time' => '09:15',
            'exit_time' => '15:25',
            'default_lots' => 1,
            'default_side' => 'SELL',
            'snapshot_minutes' => 15,
            'entry_price_field' => 'open',
            'snapshot_price_field' => 'close',
            'missing_data_policy' => 'skip_day',
        ];
    }

    private function uniqueSlug(string $value, ?int $ignoreId = null): string
    {
        $base = Str::slug($value) ?: 'strategy';
        $slug = $base;
        $counter = 2;

        while (DB::table('backtest_strategies')
                 ->where('slug', $slug)
                 ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
                 ->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }
}
