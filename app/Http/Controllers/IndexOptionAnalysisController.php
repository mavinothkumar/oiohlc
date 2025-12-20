<?php
namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IndexOptionAnalysisController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('index_option_analysis')
                   ->orderByDesc('trade_date')
                   ->orderBy('underlying_symbol');

        if ($request->filled('symbol')) {
            $query->where('underlying_symbol', 'like', '%'.$request->symbol.'%');
        }

        if ($request->filled('from')) {
            $query->whereDate('trade_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->whereDate('trade_date', '<=', $request->to);
        }

        if ($request->filled('atm')) {
            $query->where('atm_strike', $request->atm);
        }

        $rows = $query->paginate(25)->withQueryString(); // Tailwind-ready pagination [web:74][web:71]

        return view('analysis.index', compact('rows'));
    }
}
