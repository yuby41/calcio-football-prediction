<?php

namespace App\Console\Commands;

use App\Models\Team;
use Illuminate\Console\Command;

class FixCountryLeagueMapping extends Command
{
    protected $signature = 'fix:country-league-mapping';
    protected $description = 'Fix country assignment for all teams based on their league';

    private array $leagueToCountryMapping = [
        // England
        'Premier League' => 'England',
        'Championship' => 'England',
        'League One' => 'England',
        'League Two' => 'England',
        'National League' => 'England',
        'National League - Central' => 'England',
        'National League - North' => 'England',
        'National League - Northern' => 'England',
        'National League - Southern' => 'England',
        'Premier League 2 Division One' => 'England',
        'Non League Div One - Southern Central' => 'England',
        'Non League Div One - Southern South' => 'England',
        'Non League Premier - Isthmian' => 'England',
        'Non League Premier - Northern' => 'England',
        'Non League Premier - Southern Central' => 'England',
        'Non League Premier - Southern South' => 'England',
        'FA Cup' => 'England',
        'League Cup' => 'England',

        // Spain
        'La Liga' => 'Spain',
        'Segunda División' => 'Spain',

        // France
        'Ligue 1' => 'France',
        'Ligue 2' => 'France',
        'National 1' => 'France',
        'National 2 - Group A' => 'France',
        'National 2 - Group B' => 'France',
        'National 2 - Group C' => 'France',

        // Germany
        'Bundesliga' => 'Germany',
        '2. Bundesliga' => 'Germany',
        '3. Liga' => 'Germany',
        'Regionalliga - Bayern' => 'Germany',
        'Regionalliga - Mitte' => 'Germany',
        'Regionalliga - Nord' => 'Germany',
        'Regionalliga - Nordost' => 'Germany',
        'Regionalliga - Ost' => 'Germany',
        'Regionalliga - SudWest' => 'Germany',
        'Regionalliga - West' => 'Germany',
        'Oberliga - Baden-Württemberg' => 'Germany',
        'Oberliga - Bayern Nord' => 'Germany',
        'Oberliga - Bayern Süd' => 'Germany',
        'Oberliga - Bremen' => 'Germany',
        'Oberliga - Hamburg' => 'Germany',
        'Oberliga - Hessen' => 'Germany',
        'Oberliga - Niederrhein' => 'Germany',
        'Oberliga - Niedersachsen' => 'Germany',
        'Oberliga - Nordost-Nord' => 'Germany',
        'Oberliga - Nordost-Süd' => 'Germany',
        'Oberliga - Rheinland-Pfalz / Saar' => 'Germany',
        'Oberliga - Schleswig-Holstein' => 'Germany',
        'Oberliga - Westfalen' => 'Germany',
        'DFB Junioren Pokal' => 'Germany',
        'DFB Pokal - Women' => 'Germany',
        'U19 Bundesliga' => 'Germany',
        'Bundesliga' => 'Germany',
        'Frauenliga' => 'Germany',

        // Italy
        'Serie A' => 'Italy',
        'Serie B' => 'Italy',
        'Serie C' => 'Italy',
        'Serie D' => 'Italy',
        'Coppa Italia' => 'Italy',
        'Coppa Italia Serie C' => 'Italy',
        'Campionato Primavera - 1' => 'Italy',

        // Portugal
        'Liga Pro' => 'Portugal',
        'Liga Pro Serie B' => 'Portugal',
        'Campeonato de Portugal Prio - Group A' => 'Portugal',
        'Campeonato de Portugal Prio - Group B' => 'Portugal',
        'Campeonato de Portugal Prio - Group C' => 'Portugal',
        'Campeonato de Portugal Prio - Group D' => 'Portugal',
        'Liga Revelação U23' => 'Portugal',
        'Primeira Divisão' => 'Portugal',

        // Netherlands
        'Eredivisie' => 'Netherlands',
        'Eerste Divisie' => 'Netherlands',
        'Derde Divisie - Saturday' => 'Netherlands',
        'Derde Divisie - Sunday' => 'Netherlands',

        // Belgium
        'Jupiler Pro League' => 'Belgium',
        'Challenger Pro League' => 'Belgium',

        // Scotland
        'Premiership' => 'Scotland',
        'Championship' => 'Scotland',
        'First Division' => 'Scotland',
        'Second Division' => 'Scotland',
        'Football League - Highland League' => 'Scotland',
        'Football League - Lowland League' => 'Scotland',
        'Premiership Women' => 'Scotland',

        // Wales
        'FAW Championship' => 'Wales',

        // Ireland
        'Premier Division' => 'Ireland',
        'First Division' => 'Ireland',
        'FAI Cup' => 'Ireland',

        // Nordic Countries
        'Allsvenskan' => 'Sweden',
        'Superettan' => 'Sweden',
        'Ettan - Norra' => 'Sweden',
        'Ettan - Södra' => 'Sweden',
        'Division 2 - Norra Götaland' => 'Sweden',
        'Division 2 - Norra Svealand' => 'Sweden',
        'Division 2 - Norrland' => 'Sweden',
        'Division 2 - Östra Götaland' => 'Sweden',
        'Division 2 - Södra Svealand' => 'Sweden',
        'Division 2 - Västra Götaland' => 'Sweden',
        'Damallsvenskan' => 'Sweden',
        'Elitettan' => 'Sweden',
        'Svenska Cupen' => 'Sweden',
        'Svenska Cupen - Women' => 'Sweden',

        'Eliteserien' => 'Norway',
        'Toppserien' => 'Norway',

        'Superliga' => 'Denmark',
        'First NL' => 'Denmark',
        'Denmark Series - Group 1' => 'Denmark',
        'Denmark Series - Group 2' => 'Denmark',
        'Denmark Series - Group 3' => 'Denmark',
        'Denmark Series - Group 4' => 'Denmark',
        'DBU Pokalen' => 'Denmark',
        'Kvindeliga' => 'Denmark',

        'Veikkausliiga' => 'Finland',
        'Ykkönen' => 'Finland',
        'Ykkösliiga' => 'Finland',
        'Kakkonen - Lohko A' => 'Finland',
        'Kakkonen - Lohko B' => 'Finland',
        'Kakkonen - Lohko C' => 'Finland',
        'Kansallinen Liiga' => 'Finland',

        'Úrvalsdeild' => 'Iceland',
        'Úrvalsdeild Women' => 'Iceland',
        '1. Deild' => 'Iceland',
        '2. Deild' => 'Iceland',
        'Meistaradeildin' => 'Iceland',
        'Fotbolti.net Cup A' => 'Iceland',

        // Eastern Europe
        'Ekstraklasa' => 'Poland',
        'I Liga' => 'Poland',
        'I Liga - Women' => 'Poland',
        'II Liga - East' => 'Poland',
        'III Liga - Group 1' => 'Poland',
        'III Liga - Group 2' => 'Poland',
        'III Liga - Group 3' => 'Poland',
        'III Liga - Group 4' => 'Poland',
        'Ekstraliga Women' => 'Poland',

        'Czech Liga' => 'Czech Republic',
        'FNL' => 'Czech Republic',
        'Druha Liga' => 'Czech Republic',
        '3. liga - Center' => 'Czech Republic',
        '3. liga - CFL A' => 'Czech Republic',
        '3. liga - CFL B' => 'Czech Republic',
        '3. liga - East' => 'Czech Republic',
        '3. liga - MSFL' => 'Czech Republic',
        '3. liga - West' => 'Czech Republic',

        'Liga I' => 'Romania',
        'Liga II' => 'Romania',
        'Cupa României' => 'Romania',

        'NB I' => 'Hungary',
        'NB II' => 'Hungary',
        'NB III - Northeast' => 'Hungary',
        'NB III - Northwest' => 'Hungary',
        'NB III - Southeast' => 'Hungary',
        'NB III - Southwest' => 'Hungary',
        'Magyar Kupa' => 'Hungary',

        'Persha Liga' => 'Ukraine',
        'Prva Liga' => 'Ukraine',

        // Former Yugoslavia
        'HNL' => 'Croatia',
        'Premijer Liga' => 'Bosnia and Herzegovina',
        '1. SNL' => 'Slovenia',
        '2. SNL' => 'Slovenia',
        '1st League - RS' => 'Serbia',

        // Baltic States
        'Meistriliiga' => 'Estonia',
        'Esiliiga A' => 'Estonia',
        'Esiliiga B' => 'Estonia',

        'Virsliga' => 'Latvia',

        'A Lyga' => 'Lithuania',
        '1 Lyga' => 'Lithuania',

        // Other European
        'Super Liga' => 'Serbia',
        'Erovnuli Liga' => 'Georgia',
        'Erovnuli Liga 2' => 'Georgia',
        'David Kipiani Cup' => 'Georgia',
        'Premyer Liqa' => 'Azerbaijan',

        // Switzerland
        'Super League' => 'Switzerland',
        'Challenge League' => 'Switzerland',
        'Schweizer Cup' => 'Switzerland',

        // Austria
        'Bundesliga' => 'Austria',
        '2. Liga' => 'Austria',

        // Turkey
        'Süper Lig' => 'Turkey',
        '1. Lig' => 'Turkey',

        // Russia/Belarus
        'Vysshaya Liga' => 'Belarus',
        'Ýokary Liga' => 'Turkmenistan',

        // America
        'Major League Soccer' => 'United States',
        'MLS Next Pro' => 'United States',
        'USL Championship' => 'United States',
        'USL League One' => 'United States',
        'USL League One Cup' => 'United States',
        'USL League Two' => 'United States',
        'Leagues Cup' => 'United States',

        'Canadian Premier League' => 'Canada',
        'Canadian Soccer League' => 'Canada',
        'League 1 Ontario' => 'Canada',

        'Liga MX' => 'Mexico',
        'Liga de Expansión MX' => 'Mexico',
        'Liga MX Femenil' => 'Mexico',

        // South America
        'Liga Profesional Argentina' => 'Argentina',
        'Primera Nacional' => 'Argentina',
        'Primera B' => 'Argentina',
        'Primera B Metropolitana' => 'Argentina',
        'Primera C' => 'Argentina',
        'Copa Argentina' => 'Argentina',
        'Torneo Federal A' => 'Argentina',
        'Torneo Promocional Amateur' => 'Argentina',

        'Brasileiro Women' => 'Brazil',
        'Brasileiro U17' => 'Brazil',
        'Brasileiro U20 A' => 'Brazil',
        'Copa Paulista' => 'Brazil',
        'Copa Rio' => 'Brazil',
        'Copa Espírito Santo' => 'Brazil',
        'Paulista Série B' => 'Brazil',
        'Paulista - U20' => 'Brazil',
        'Carioca A2' => 'Brazil',
        'Carioca C' => 'Brazil',
        'Carioca U20' => 'Brazil',
        'Mineiro - 2' => 'Brazil',
        'Mineiro U20' => 'Brazil',
        'Gaúcho - 2' => 'Brazil',
        'Catarinense - 2' => 'Brazil',
        'Catarinense U20' => 'Brazil',
        'Baiano - 2' => 'Brazil',
        'Baiano U20' => 'Brazil',
        'Cearense - 3' => 'Brazil',
        'Cearense U20' => 'Brazil',
        'Alagoano - 2' => 'Brazil',
        'Alagoano U20' => 'Brazil',
        'Goiano - 2' => 'Brazil',
        'Matogrossense 2' => 'Brazil',
        'Paraense U20' => 'Brazil',
        'Paraibano U20' => 'Brazil',
        'Paranaense U20' => 'Brazil',
        'Potiguar - U20' => 'Brazil',
        'Capixaba B' => 'Brazil',
        'Brasiliense U20' => 'Brazil',

        'Primera División' => 'Chile',
        'Primera B' => 'Chile',

        'Liga Pro' => 'Ecuador',
        'Liga Pro Serie B' => 'Ecuador',
        'Copa Ecuador' => 'Ecuador',

        'Liga Nacional' => 'Paraguay',
        'División Profesional - Clausura' => 'Paraguay',
        'División Intermedia' => 'Paraguay',
        'Copa Paraguay' => 'Paraguay',

        'Copa Uruguay' => 'Uruguay',

        'Liga Femenina' => 'Venezuela',
        'Copa Venezuela' => 'Venezuela',

        'Copa Colombia' => 'Colombia',
        'Primera A' => 'Colombia',

        // Central America
        'Liga Panameña de Fútbol' => 'Panama',
        'Copa de la División Profesional' => 'Panama',
        'Liga de Ascenso' => 'Costa Rica',
        'Liga Mayor' => 'Costa Rica',

        // Asia
        'J1 League' => 'Japan',
        'J2 League' => 'Japan',
        'J3 League' => 'Japan',
        'Japan Football League' => 'Japan',
        'Emperor Cup' => 'Japan',
        'WE League' => 'Japan',

        'K League 1' => 'South Korea',
        'K League 2' => 'South Korea',
        'K3 League' => 'South Korea',
        'WK-League' => 'South Korea',

        'Persian Gulf Pro League' => 'Iran',

        'Thai League 1' => 'Thailand',
        'Thai League 2' => 'Thailand',

        'V.League 1' => 'Vietnam',

        'Lao League' => 'Laos',

        'Taiwan Football Premier League' => 'Taiwan',

        'Stars League' => 'Qatar',
        'Pro League' => 'UAE',
        'Pro League A' => 'UAE',

        // Australia/New Zealand
        'A-League' => 'Australia',
        'Australia Cup' => 'Australia',
        'Brisbane Premier League' => 'Australia',
        'New South Wales NPL' => 'Australia',
        'New South Wales NPL 2' => 'Australia',
        'Northern NSW NPL' => 'Australia',
        'Queensland NPL' => 'Australia',
        'Queensland Premier League' => 'Australia',
        'Victoria NPL' => 'Australia',
        'Victoria NPL 2' => 'Australia',
        'South Australia NPL' => 'Australia',
        'South Australia State League 1' => 'Australia',
        'Western Australia NPL' => 'Australia',
        'Western Australia State League 1' => 'Australia',
        'Tasmania NPL' => 'Australia',
        'Tasmania Northern Championship' => 'Australia',
        'Tasmania Southern Championship' => 'Australia',
        'Northern Territory Premier League' => 'Australia',
        'Capital Territory NPL' => 'Australia',
        'Capital Territory NPL 2' => 'Australia',
        'NNSW League 1' => 'Australia',

        'Chatham Cup' => 'New Zealand',

        // India
        'Calcutta Premier Division' => 'India',
        'National Football League' => 'India',

        // Africa
        'Premier Soccer League' => 'South Africa',
        'National Division' => 'South Africa',

        // International/Continental
        'UEFA Champions League' => 'Europe',
        'UEFA Europa League' => 'Europe',
        'UEFA Europa Conference League' => 'Europe',
        'UEFA Champions League Women' => 'Europe',
        'UEFA Championship - Women' => 'Europe',

        'AFC Champions League' => 'Asia',
        'AFC Cup' => 'Asia',
        'AFC Challenge League' => 'Asia',
        'AFC U20 Asian Cup - Women' => 'Asia',
        'AFF U23 Championship' => 'Asia',
        'ASEAN Club Championship' => 'Asia',
        'Asean Championship Women' => 'Asia',

        'CONCACAF Caribbean Club Shield' => 'North America',
        'Concacaf Central American Cup' => 'North America',

        'Copa America Femenina' => 'South America',

        'Africa Cup of Nations - Women' => 'Africa',
        'African Nations Championship' => 'Africa',

        // Additional mappings for remaining leagues
        'Primera Division' => 'Costa Rica', // Based on Municipal Liberia, CS Cartagines
        'Liga 1' => 'Moldova', // Based on Floreşti, Gagauziya-Oguzsport
        'Toto Cup Ligat Al' => 'Israel', // Based on Hapoel, Maccabi teams
        '1. Liga' => 'Latvia', // Based on Ogre United, Rīgas FS
        '2. liga' => 'Slovakia', // Based on Inter Bratislava, Žilina
        'Liga 3' => 'Portugal', // Based on SC Braga B, Mafra
        'Segunda Liga' => 'Portugal', // Portuguese second division
        '1. Division' => 'Denmark', // Danish division
        '2. Division' => 'Denmark', // Danish division
        '2. Division - Group 1' => 'Denmark',
        '2. Division - Group 2' => 'Denmark',
        '3. Division' => 'Denmark',
        '3. Division - Girone 1' => 'Italy', // Italian regional divisions
        '3. Division - Girone 2' => 'Italy',
        '3. Division - Girone 3' => 'Italy',
        '3. Division - Girone 4' => 'Italy',
        '3. Division - Girone 5' => 'Italy',
        '3. Division - Girone 6' => 'Italy',
        'Second League' => 'Bulgaria', // Bulgarian second league
        'Second League - Group 1' => 'Bulgaria',
        'Second League - Group 2' => 'Bulgaria',
        'Second League - Group 3' => 'Bulgaria',
        'Second League - Group 4' => 'Bulgaria',
        'First League' => 'Bulgaria', // Bulgarian first league
        'Supreme Division Women' => 'England', // English women's league
        'All-Island Cup - Women' => 'Ireland', // Irish women's cup
        'Coppa' => 'Italy', // Italian cup
        'Super Cup' => 'International', // Various super cups
        'Cup' => 'International', // Generic cup competitions
        'League' => 'International', // Generic league
        'Reserve League' => 'International', // Reserve teams
        'U19 League' => 'International', // Youth leagues
        'Youth Championship' => 'International', // Youth competitions
        '1. Liga U19' => 'Germany', // German youth
        '1. Liga Women' => 'Germany', // German women
        '1. Liga Promotion' => 'Germany', // German promotion
        '1. Liga Classic - Group 1' => 'Germany',
        '1. Liga Classic - Group 2' => 'Germany',
        '1. Liga Classic - Group 3' => 'Germany',
        'Division Intermedia' => 'Paraguay', // Already mapped but fixing
        'Division Profesional - Clausura' => 'Paraguay', // Already mapped but fixing
        'Segunda Liga' => 'Portugal', // Portuguese second tier
        'Professional League' => 'International',
        'Professional Development League' => 'International',
        'Challenge Cup' => 'International',
        'National League Cup' => 'England',
        'Pacific Coast Soccer League' => 'United States',
        'Northern Super League' => 'Canada',
        'NWSL Women' => 'United States',
        'Central Youth League' => 'International',
        'C-League' => 'International',
        'Nasjonal U19 Champions League' => 'Norway',
        'U18 Premier League - Championship' => 'England',
        'Júniores U19' => 'Portugal',
        'Ligue A' => 'France',
        'Third League - Northeast' => 'Bulgaria',
        'Third League - Southeast' => 'Bulgaria', 
        'Third League - Southwest' => 'Bulgaria',
        '4. liga - Divizie A' => 'Czech Republic',
        '4. liga - Divizie B' => 'Czech Republic',
        '4. liga - Divizie C' => 'Czech Republic',
        '4. liga - Divizie D' => 'Czech Republic',
        '4. liga - Divizie E' => 'Czech Republic',
        '4. liga - Divizie F' => 'Czech Republic',
        'Second League A - Fall Season Gold' => 'United States',
        'Second League A - Fall Season Silver' => 'United States',
        'Primera División - Clausura' => 'Uruguay', // Based on Peñarol, Nacional
        '1. Division Women' => 'Norway', // Based on Molde W, Viking FK
        '8 Cup' => 'South Africa', // Based on Orlando Pirates, Mamelodi Sundowns

        // Other/Unknown
        'Friendlies' => 'International',
        'Friendlies Clubs' => 'International',
        'COTIF Tournament' => 'International',
    ];

    public function handle(): int
    {
        $this->info('Starting country-league mapping fix...');

        $totalUpdated = 0;
        $notMapped = [];

        // Get all distinct leagues that don't have country assigned
        $teams = Team::whereNull('country')->orWhere('country', '')->get();

        foreach ($teams as $team) {
            $league = $team->league;
            
            if (isset($this->leagueToCountryMapping[$league])) {
                $country = $this->leagueToCountryMapping[$league];
                $team->update(['country' => $country]);
                $totalUpdated++;
                
                if ($totalUpdated % 100 == 0) {
                    $this->info("Updated $totalUpdated teams...");
                }
            } else {
                if (!in_array($league, $notMapped)) {
                    $notMapped[] = $league;
                }
            }
        }

        $this->info("✅ Updated $totalUpdated teams with correct country assignment");
        
        if (!empty($notMapped)) {
            $this->warn("⚠️  Leagues without mapping (" . count($notMapped) . "):");
            foreach ($notMapped as $league) {
                $this->line("  - $league");
            }
        }

        // Show final stats
        $this->newLine();
        $this->info('Final statistics:');
        $totalTeams = Team::count();
        $teamsWithCountry = Team::whereNotNull('country')->where('country', '!=', '')->count();
        $teamsWithoutCountry = Team::whereNull('country')->orWhere('country', '')->count();
        
        $this->table(['Metric', 'Count', 'Percentage'], [
            ['Total teams', $totalTeams, '100%'],
            ['Teams with country', $teamsWithCountry, round(($teamsWithCountry / $totalTeams) * 100, 2) . '%'],
            ['Teams without country', $teamsWithoutCountry, round(($teamsWithoutCountry / $totalTeams) * 100, 2) . '%'],
        ]);

        return 0;
    }
}