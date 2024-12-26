<?php

namespace App\Http\Controllers\Clickup;

use App\Http\Controllers\Controller;
use App\Models\Ticket\ClickupTicket;
use Illuminate\Http\Request;

class Webhook extends Controller
{
    public function updateTask(Request $request)
    {
        try {
            switch ($request->event) {
                case 'taskStatusUpdated':
                    $this->proccessStatusUpdated($request);
                    break;
                default:
                    break;
            }
        } catch (\Exception $e) {
            return self::basicError("Error Webhook".$e->getMessage());
        }
    }

    public function proccessStatusUpdated($request)
    {
        $ticket = ClickupTicket::where('ClickupTaskId', $request->task_id)->first();
        if ($ticket) {
            foreach ($request->history_items as $item) {
                if ($item['field'] == 'status') {
                    ClickupTicket::where('ClickupTaskId', $request->task_id)->update([
                        'TicketStatus' => $item['after']['status'],
                        'StatusColor' => $item['after']['color'],
                    ]);
                }
            }
        }
    }
}
