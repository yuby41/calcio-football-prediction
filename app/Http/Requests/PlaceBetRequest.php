<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Constants\BettingConstants;

class PlaceBetRequest extends FormRequest
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
            'match_id' => [
                'required',
                'integer',
                'exists:matches,id'
            ],
            'bet_type' => [
                'required',
                'string',
                'in:' . implode(',', [
                    BettingConstants::BET_TYPE_MATCH_OUTCOME,
                    BettingConstants::BET_TYPE_OVER_UNDER_2_5,
                    BettingConstants::BET_TYPE_BOTH_TEAMS_SCORE,
                    BettingConstants::BET_TYPE_FIRST_HALF_OVER_0_5
                ])
            ],
            'predicted_outcome' => [
                'required',
                'string',
                'in:' . implode(',', [
                    BettingConstants::OUTCOME_HOME_WIN,
                    BettingConstants::OUTCOME_AWAY_WIN,
                    BettingConstants::OUTCOME_DRAW,
                    BettingConstants::OUTCOME_OVER_2_5,
                    BettingConstants::OUTCOME_UNDER_2_5,
                    BettingConstants::OUTCOME_YES,
                    BettingConstants::OUTCOME_NO
                ])
            ],
            'amount' => [
                'required',
                'numeric',
                'min:' . BettingConstants::MIN_BET_AMOUNT,
                'max:1000' // Reasonable maximum
            ],
            'odds' => [
                'required',
                'numeric',
                'min:' . BettingConstants::MIN_ACCEPTABLE_ODDS,
                'max:' . BettingConstants::MAX_ACCEPTABLE_ODDS
            ],
            'confidence_score' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100'
            ]
        ];
    }

    /**
     * Get custom error messages for validation rules.
     */
    public function messages(): array
    {
        return [
            'match_id.required' => 'Debe seleccionar un partido.',
            'match_id.exists' => 'El partido seleccionado no existe.',
            'bet_type.required' => 'Debe seleccionar un tipo de apuesta.',
            'bet_type.in' => 'El tipo de apuesta no es válido.',
            'predicted_outcome.required' => 'Debe seleccionar un resultado.',
            'predicted_outcome.in' => 'El resultado seleccionado no es válido.',
            'amount.required' => 'El monto de la apuesta es obligatorio.',
            'amount.min' => 'El monto mínimo de apuesta es $' . BettingConstants::MIN_BET_AMOUNT,
            'amount.max' => 'El monto máximo de apuesta es $1000',
            'odds.required' => 'Las cuotas son obligatorias.',
            'odds.min' => 'Las cuotas mínimas aceptadas son ' . BettingConstants::MIN_ACCEPTABLE_ODDS,
            'odds.max' => 'Las cuotas máximas aceptadas son ' . BettingConstants::MAX_ACCEPTABLE_ODDS,
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Sanitize numeric inputs
        if ($this->has('amount')) {
            $this->merge([
                'amount' => (float) str_replace(',', '', $this->amount)
            ]);
        }

        if ($this->has('odds')) {
            $this->merge([
                'odds' => (float) str_replace(',', '', $this->odds)
            ]);
        }

        // Ensure match_id is integer
        if ($this->has('match_id')) {
            $this->merge([
                'match_id' => (int) $this->match_id
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate bet amount against budget balance
            $budget = $this->route('budget');
            
            if ($budget && $this->amount) {
                if ($this->amount > $budget->available_balance) {
                    $validator->errors()->add('amount', 
                        'El monto de la apuesta excede el saldo disponible ($' . 
                        number_format($budget->available_balance, 2) . ')'
                    );
                }

                // Check maximum bet percentage
                $maxBetAmount = $budget->available_balance * BettingConstants::MAX_BET_PERCENTAGE_OF_BALANCE;
                if ($this->amount > $maxBetAmount) {
                    $validator->errors()->add('amount', 
                        'La apuesta no puede exceder el ' . 
                        (BettingConstants::MAX_BET_PERCENTAGE_OF_BALANCE * 100) . 
                        '% del saldo disponible ($' . number_format($maxBetAmount, 2) . ')'
                    );
                }
            }

            // Validate confidence vs minimum threshold
            $budget = $this->route('budget');
            if ($budget && $this->confidence_score && $budget->min_confidence_threshold) {
                if ($this->confidence_score < $budget->min_confidence_threshold) {
                    $validator->errors()->add('confidence_score', 
                        'La confianza (' . $this->confidence_score . '%) está por debajo del umbral mínimo (' . 
                        $budget->min_confidence_threshold . '%)'
                    );
                }
            }

            // Validate bet type matches predicted outcome
            if ($this->bet_type === BettingConstants::BET_TYPE_MATCH_OUTCOME) {
                $validOutcomes = [
                    BettingConstants::OUTCOME_HOME_WIN,
                    BettingConstants::OUTCOME_AWAY_WIN,
                    BettingConstants::OUTCOME_DRAW
                ];
                if (!in_array($this->predicted_outcome, $validOutcomes)) {
                    $validator->errors()->add('predicted_outcome', 
                        'El resultado no es válido para el tipo de apuesta seleccionado.'
                    );
                }
            }
        });
    }
}