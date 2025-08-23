<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\BettingConstants;

class BudgetStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Will implement proper auth later
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-zA-Z0-9\s\-_]+$/' // Only alphanumeric, spaces, hyphens, underscores
            ],
            'initial_budget' => [
                'required',
                'numeric',
                'min:' . BettingConstants::MIN_BUDGET_AMOUNT,
                'max:100000'
            ],
            'strategy_type' => [
                'required',
                'string',
                'in:' . implode(',', [
                    BettingConstants::STRATEGY_FIXED,
                    BettingConstants::STRATEGY_MARTINGALE,
                    BettingConstants::STRATEGY_MANSANIELLO,
                    BettingConstants::STRATEGY_FIBONACCI,
                    BettingConstants::STRATEGY_PERCENTAGE
                ])
            ],
            'base_bet_amount' => [
                'required',
                'numeric',
                'min:' . BettingConstants::MIN_BET_AMOUNT,
                'max:1000'
            ],
            'risk_multiplier' => [
                'required',
                'numeric',
                'min:1.1',
                'max:5.0'
            ],
            'target_profit_percentage' => [
                'required',
                'numeric',
                'min:' . BettingConstants::MIN_TARGET_PROFIT_PERCENTAGE,
                'max:' . BettingConstants::MAX_TARGET_PROFIT_PERCENTAGE
            ],
            'max_daily_loss' => [
                'nullable',
                'numeric',
                'min:10',
                'max:10000'
            ],
            'min_confidence_threshold' => [
                'required',
                'numeric',
                'min:50',
                'max:95'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del presupuesto es obligatorio.',
            'name.regex' => 'El nombre solo puede contener letras, números, espacios, guiones y guiones bajos.',
            'initial_budget.required' => 'El presupuesto inicial es obligatorio.',
            'initial_budget.min' => 'El presupuesto inicial debe ser al menos $' . BettingConstants::MIN_BUDGET_AMOUNT,
            'strategy_type.required' => 'Debe seleccionar una estrategia de apuestas.',
            'strategy_type.in' => 'La estrategia seleccionada no es válida.',
            'base_bet_amount.required' => 'El monto base de apuesta es obligatorio.',
            'base_bet_amount.min' => 'El monto base debe ser al menos $' . BettingConstants::MIN_BET_AMOUNT,
            'risk_multiplier.required' => 'El multiplicador de riesgo es obligatorio.',
            'risk_multiplier.min' => 'El multiplicador de riesgo debe ser al menos 1.1',
            'target_profit_percentage.required' => 'El objetivo de ganancia es obligatorio.',
            'min_confidence_threshold.required' => 'El umbral de confianza mínimo es obligatorio.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize numeric inputs
        if ($this->has('initial_budget')) {
            $this->merge([
                'initial_budget' => (float) str_replace(',', '', $this->initial_budget)
            ]);
        }

        if ($this->has('base_bet_amount')) {
            $this->merge([
                'base_bet_amount' => (float) str_replace(',', '', $this->base_bet_amount)
            ]);
        }

        // Sanitize string inputs
        if ($this->has('name')) {
            $this->merge([
                'name' => trim(strip_tags($this->name))
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Custom business logic validation
            if ($this->initial_budget && $this->base_bet_amount) {
                $ratio = $this->base_bet_amount / $this->initial_budget;
                
                if ($ratio > BettingConstants::MAX_BET_PERCENTAGE_OF_BALANCE) {
                    $validator->errors()->add('base_bet_amount', 
                        'El monto base no puede ser más del ' . 
                        (BettingConstants::MAX_BET_PERCENTAGE_OF_BALANCE * 100) . 
                        '% del presupuesto inicial.'
                    );
                }
            }

            // Validate strategy-specific rules
            if ($this->strategy_type === BettingConstants::STRATEGY_MARTINGALE && $this->risk_multiplier < 1.8) {
                $validator->errors()->add('risk_multiplier', 
                    'Para estrategia Martingale, el multiplicador debe ser al menos 1.8'
                );
            }
        });
    }
}