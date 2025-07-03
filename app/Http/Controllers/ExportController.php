<?php

namespace App\Http\Controllers;

use App\Exports\OrdersExport;
use Maatwebsite\Excel\Facades\Excel;

class ExportController extends Controller
{
    public function exportOrders()
    {
        return Excel::download(new OrdersExport, 'orders-' . now()->format('Y-m-d') . '.xlsx');
    }
}
