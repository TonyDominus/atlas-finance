<?php

namespace App\Http\Controllers;

use App\Enums\WealthBucket;
use App\Services\Investments\PositionReader;
use App\Services\Ledger\PortfolioReader;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WealthController extends Controller
{
    public function __invoke(Request $request, PortfolioReader $portfolio, PositionReader $positions): Response
    {
        $snapshot = $portfolio->for($request->user());
        $accounts = array_values(array_filter(
            $snapshot['accounts'],
            fn (array $account) => $account['status'] !== 'archived',
        ));

        $sections = array_map(fn (WealthBucket $bucket) => [
            'key' => $bucket->value,
            'label' => $bucket->label(),
            'hint' => $bucket->hint(),
            'accounts' => array_values(array_filter($accounts, fn (array $account) => $account['bucket'] === $bucket->value)),
        ], WealthBucket::cases());

        $book = $positions->portfolio($request->user());

        return Inertia::render('Wealth/Index', [
            'by_currency' => $snapshot['by_currency'],
            'sections' => $sections,
            'positions' => $book['positions'],
            'investments' => $book['by_currency'],
        ]);
    }
}
