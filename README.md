# Calcio — Football Prediction & ML Pipeline

A football data and machine-learning experimentation platform built with **Laravel 12, Python and MySQL**.

The project combines historical football data ingestion, reproducible ML training, temporal feature engineering and Laravel application services in a single repository.

The current ML experiment focuses on **Serie A 1X2 outcome classification** (`home_win`, `draw`, `away_win`) using causal point-in-time features.

## Overview

The project is divided into two main layers:

### Laravel application

Laravel handles:

* football data management;
* database persistence;
* historical data imports;
* API integrations;
* application services;
* prediction orchestration;
* CLI commands;
* web application functionality.

### Python ML pipeline

Python handles:

* dataset preparation;
* causal feature engineering;
* chronological train/validation/test separation;
* model training;
* time-series cross-validation;
* ensemble classification;
* model evaluation and artifact generation.

The enhanced ML pipeline uses:

* XGBoost
* LightGBM
* scikit-learn MLP
* Random Forest
* pandas
* NumPy
* joblib

## Architecture

```text
                    Historical data
                       OpenFootball
                            |
                            v
                 Laravel import service
                            |
                            v
                         MySQL
                            |
                            v
                  Python ML pipeline
                            |
             +--------------+--------------+
             |              |              |
       Point-in-time     Feature       Temporal
        statistics     engineering      split
             |              |              |
             +--------------+--------------+
                            |
                            v
             XGBoost / LightGBM / MLP / RF
                            |
                            v
                    1X2 Ensemble Model
                            |
                            v
                Evaluation / Model artifacts


Operational / application layer:

API-Sports ---> Laravel services ---> Database / application
```

Historical model training is intentionally separated from the operational API integration.

This allows the ML experiment to be reproduced without requiring an API key.

## Historical Dataset

The enhanced experiment uses historical **Serie A** data imported from OpenFootball.

Current dataset:

| Property                    |             Value |
| --------------------------- | ----------------: |
| Seasons                     |                 5 |
| Period                      | 2020-21 → 2024-25 |
| Finished matches            |             1,890 |
| Teams                       |                28 |
| Matches with half-time data |             1,770 |

Matches without a final score are excluded from the training dataset.

Historical records use deterministic identifiers during import so that the ingestion process can be rerun without creating duplicate fixtures.

## Avoiding Temporal Data Leakage

One of the main goals of the enhanced pipeline is preventing future information from leaking into historical training examples.

For every match, team statistics are calculated using only matches that occurred **before that fixture**.

Examples include:

* previous matches played;
* wins, draws and losses;
* goals scored and conceded;
* points per game;
* goal difference;
* attacking and defensive strength;
* recent form indicators.

Statistics are reset at the beginning of each season.

The point-in-time implementation was checked against basic causal invariants, including verifying that a team's first appearance of each season contains zero prior matches.

## Feature Engineering

The final Enhanced v3 classifier uses **40 features**.

Feature groups include:

* win/draw/loss rates;
* scoring and conceding rates;
* goal difference;
* points per game;
* attack and defense strength;
* home/away strength comparisons;
* normalized attack and defense indicators;
* season progression;
* recent-form trends;
* form momentum;
* consistency indicators.

Features are constructed chronologically before the temporal dataset split.

## Temporal Evaluation Strategy

Random train/test splitting is intentionally avoided.

The dataset is divided chronologically:

| Dataset       | Seasons           | Matches |
| ------------- | ----------------- | ------: |
| Training      | 2020-21 → 2022-23 |   1,140 |
| Validation    | 2023-24           |     380 |
| Final holdout | 2024-25           |     370 |

The final 2024-25 season was excluded from model training and model selection and was evaluated once after the model pipeline was frozen.

The training set also uses a five-fold `TimeSeriesSplit` for chronological cross-validation.

## Ensemble

Enhanced v3 combines four classifiers:

* XGBoost
* LightGBM
* Multi-Layer Perceptron
* Random Forest

The frozen ensemble currently uses equal weights:

```text
XGBoost        25%
LightGBM       25%
MLP            25%
Random Forest  25%
```

A majority-class classifier is used as the baseline.

## Results

### Validation — 2023-24

| Metric            | Majority baseline |  Ensemble |
| ----------------- | ----------------: | --------: |
| Accuracy          |             41.8% | **47.1%** |
| Balanced Accuracy |             33.3% | **45.9%** |
| Macro F1          |             0.197 | **0.438** |
| Log Loss          |                 — |     1.190 |

Training-only time-series cross-validation accuracies:

```text
Fold 1: 49.5%
Fold 2: 44.2%
Fold 3: 43.7%
Fold 4: 43.7%
Fold 5: 42.6%
```

### Final Holdout — 2024-25

| Metric            | Majority baseline | Frozen Ensemble |
| ----------------- | ----------------: | --------------: |
| Accuracy          |             40.3% |       **44.1%** |
| Balanced Accuracy |             33.3% |       **43.1%** |
| Macro F1          |             0.191 |       **0.408** |
| Log Loss          |                 — |           1.176 |

These metrics are reported as experimental results rather than production accuracy claims.

The final holdout was not subsequently used to tune the model.

## Historical Data Import

Historical Serie A data can be imported with:

```bash
php artisan football:import-open-history \
    --competition=serie-a \
    --from=2020-21 \
    --to=2024-25
```

The importer:

1. downloads the requested OpenFootball datasets;
2. validates the source records;
3. creates deterministic team and match identifiers;
4. stores completed fixtures;
5. skips records without final scores;
6. supports repeated execution without duplicating imported fixtures.

A dry-run mode is also available.

## Installation

### Laravel

Requirements:

* PHP 8.2+
* Composer
* MySQL
* PHP extensions required by Laravel and project dependencies

Install PHP dependencies:

```bash
composer install
```

Create the environment file:

```bash
cp .env.example .env
php artisan key:generate
```

Configure the database in `.env`, then run:

```bash
php artisan migrate
```

### Python ML environment

Python 3.12 was used for the current experiment.

Create the ML environment with:

```bash
./setup_ml_env.sh
```

Or activate an existing environment:

```bash
source ml_env/bin/activate
```

Verify the environment:

```bash
python check_ml_env.py
```

## Training Enhanced v3

After importing the historical dataset:

```bash
ml_env/bin/python ml/enhanced_football_predictor.py train
```

Generated binary model artifacts are stored under:

```text
ml/models/
```

Binary `.pkl` artifacts are intentionally excluded from Git.

The repository contains the code and dependency definitions necessary to reproduce training instead of versioning generated model binaries.

## External Data Services

### OpenFootball

Used for reproducible historical training data.

The ML training workflow does not require an external API key.

### API-Sports / API-Football

Used by the Laravel operational data layer.

Configure when required:

```env
FOOTBALL_API_KEY=your_api_key_here
FOOTBALL_API_BASE_URL=https://v3.football.api-sports.io
```

API availability and request limits depend on the external account and plan and are therefore not assumed by the ML training pipeline.

### Football-Data.org

The codebase also contains an experimental secondary integration used by parts of the Laravel statistics layer.

It should not be considered a complete alternative historical-data pipeline.

## Project Structure

```text
app/
├── Console/Commands/
├── Models/
└── Services/
    └── OpenFootball/

config/
database/
├── migrations/
└── schema/

ml/
├── enhanced_football_predictor.py
├── football_predictor.py
├── config.py
├── requirements.txt
└── models/

tests/
```

## Testing Notes

The repository contains Laravel unit and feature tests.

The current legacy test setup uses SQLite, while one historical performance-index migration contains MySQL-specific index inspection SQL. That migration is not currently portable to SQLite and prevents the complete legacy test suite from migrating the test database successfully.

This is separate from the Python Enhanced v3 training/evaluation pipeline.

The Python environment and model scripts can be verified with:

```bash
ml_env/bin/python check_ml_env.py

ml_env/bin/python -m py_compile \
    ml/enhanced_football_predictor.py \
    ml/football_predictor.py
```

## Current Scope and Limitations

Enhanced v3 is currently an **offline 1X2 classification experiment**.

Online Enhanced v3 inference is intentionally disabled until the same 40-feature point-in-time contract used during training can be reconstructed for live fixtures without introducing train/serve skew.

Other current limitations:

* the experiment currently focuses on Serie A;
* probability calibration has not been optimized;
* external API functionality depends on third-party service availability;
* some older Laravel functionality predates the current Enhanced v3 ML pipeline;
* the repository contains legacy application functionality that is being progressively separated from the reproducible ML experiment.

## Engineering Focus

This project is primarily intended to demonstrate work with:

* Laravel service architecture;
* Python/Laravel interoperability;
* relational data modeling;
* external APIs;
* historical data ingestion;
* idempotent import processes;
* pandas-based data processing;
* ML feature engineering;
* prevention of temporal data leakage;
* chronological model evaluation;
* ensemble classification;
* reproducible Python environments;
* Git-based project maintenance.

## Status

Enhanced v3 experiment:

```text
Historical ingestion        ✓
Point-in-time statistics    ✓
Temporal dataset split      ✓
Time-series cross-validation ✓
Four-model ensemble         ✓
Untouched final holdout     ✓
Reproducible Python setup   ✓
Online Enhanced v3 serving  Planned
```
