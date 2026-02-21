<form method="GET" action="{{ route('test.zones.index') }}" class="bg-white shadow rounded-lg p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">

        {{-- Instrument Type --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Instrument Type
            </label>
            <select name="instrument_type" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <option value="">All</option>
                @foreach($instrumentTypes as $type)
                    <option value="{{ $type }}" @selected(($filters['instrument_type'] ?? '') === $type)>
                        {{ $type }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Underlying Symbol --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Underlying Symbol
            </label>
            <select name="underlying_symbol" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <option value="">All</option>
                @foreach($underlyings as $u)
                    <option value="{{ $u }}" @selected(($filters['underlying_symbol'] ?? '') === $u)>
                        {{ $u }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- Expiry --}}
{{--        <div>--}}
{{--            <label class="block text-sm font-medium text-gray-700 mb-1">--}}
{{--                Expiry--}}
{{--            </label>--}}
{{--            <select name="expiry" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">--}}
{{--                <option value="">All</option>--}}
{{--                @foreach($expiries as $exp)--}}
{{--                    <option value="{{ $exp }}" @selected(($filters['expiry'] ?? '') === $exp)>--}}
{{--                        {{ $exp }}--}}
{{--                    </option>--}}
{{--                @endforeach--}}
{{--            </select>--}}
{{--        </div>--}}

        {{-- Interval (day / 5minute) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Interval
            </label>
            <select name="interval" class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                <option value="">All</option>
                @foreach($intervals as $int)
                    <option value="{{ $int }}" @selected(($filters['interval'] ?? '') === $int)>
                        {{ $int }}
                    </option>
                @endforeach
            </select>
        </div>

        {{-- From Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                From Date
            </label>
            <input type="date"
                name="from_date"
                value="{{ $filters['from_date'] ?? '' }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        {{-- To Date --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                To Date
            </label>
            <input type="date"
                name="to_date"
                value="{{ $filters['to_date'] ?? '' }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        {{-- Strike (optional, for options) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Strike (optional)
            </label>
            <input type="number"
                step="0.01"
                name="strike"
                value="{{ $filters['strike'] ?? '' }}"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>

        {{-- Exchange (optional) --}}
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
                Exchange
            </label>
            <input type="text"
                name="exchange"
                value="{{ $filters['exchange'] ?? '' }}"
                placeholder="e.g. NSE"
                class="block w-full rounded-md border-gray-300 shadow-sm focus:ring-indigo-500 focus:border-indigo-500 text-sm">
        </div>
    </div>

    <div class="mt-4 flex items-center gap-2">
        <button type="submit"
            class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none">
            Apply Filters
        </button>

        <a href="{{ route('test.zones.index') }}"
            class="text-sm text-gray-600 hover:text-gray-800">
            Clear
        </a>
    </div>
</form>
