<!-- resources/views/components/market-filters.blade.php -->
<div class="bg-white p-4 rounded shadow flex flex-wrap gap-4 items-center">
    <div>
        <label class="font-semibold mr-2">Market Type:</label>
        <select class="border rounded p-1" wire:model="marketType" id="marketType">
            <option value="nifty">Nifty</option>
            <option value="banknifty">BankNifty</option>
            <option value="sensex">Sensex</option>
            <option value="equity">Equity</option>
        </select>
    </div>
    <div>
        <label class="mr-2">Instrument:</label>
        <select class="border rounded p-1" wire:model="instrumentType" id="instrumentType">
            <option value="fut">Futures</option>
            <option value="opt">Options</option>
        </select>
    </div>
    <div x-show="instrumentType === 'opt'">
        <label class="mr-2">Strike Price:</label>
        <input class="border rounded p-1 w-24" type="number" wire:model="strikePrice" placeholder="Strike Price"/>
        <select class="border rounded p-1" wire:model="optionType">
            <option value="CE">CE</option>
            <option value="PE">PE</option>
        </select>
    </div>
    <div x-show="marketType === 'equity'">
        <label class="mr-2">Symbol:</label>
        <input class="border rounded p-1 w-32" wire:model="equitySymbol" placeholder="EX: TCS"/>
    </div>
    <!-- Add timeframe selector if needed -->
</div>
