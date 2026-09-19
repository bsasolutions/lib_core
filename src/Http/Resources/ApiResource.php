<?php

namespace Bsa\Core\Http\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    public function toArrayApi($request): array
    {
        $request['created_at'] = $this->created_at ? Carbon::make($this->created_at)->format('Y-m-d') : now()->format('Y-m-d');

        return $request;

        /*private array $types = ['C' => 'Cartão', 'B' => 'Boleto', 'P' => 'Pix'];
        $paid = $this->paid;
        return [
            'user' => [
                'firstName' => $this->user->firstName,
                'lastName' => $this->user->lastName,
                'fullName' => $this->user->firstName . ' ' . $this->user->lastName,
                'email' => $this->user->email,
            ],
            'type' => $this->types[$this->type],
            'value' => 'R$ ' . number_format($this->value, 2, ',', '.'),
            'paid' => $paid ? 'Pago' : 'Não Pago',
            'paymentDate' => $paid ? Carbon::parse($this->payment_date)->format('d/m/Y H:i:s') : Null,
            'paymentSince' => $paid ? Carbon::parse($this->payment_date)->diffForHumans() : Null,
            'fullName' => $this->firstName . ' ' . $this->lastName,
            'identify' => $this->id,
            'name' => strtoupper($this->name),
            'email' => $this->email,
            'created' => Carbon::make($this->created_at)->format('Y-m-d'),
        ];*/
    }
}
