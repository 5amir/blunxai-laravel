<?php

return [
    'delete_confirm' => 'Supprimer cet insight ?',
    'download_report' => 'Télécharger le rapport complet',
    'mark_read' => 'Lu',
    'report_title' => 'Rapport Blunx AI',
    'generated_at' => 'Rapport généré le',
    'footer' => 'Rapport généré automatiquement par Blunx AI. Usage interne uniquement.',
    'severity.critical' => 'Critique',
    'severity.warning' => 'Attention',
    'severity.info' => 'Informatif',
    'type.anomaly' => 'Anomalie détectée',
    'type.trend' => 'Tendance notable',
    'type.record' => 'Record détecté',
    'type.info' => 'Rapport périodique',
    'no_data_message' => 'Le widget ":title" ne retourne aucune donnée — possible interruption de flux.',
    'no_data_indicator' => 'Volume de données',
    'no_data_value' => '0 ligne',
    'no_data_previous' => ':count lignes',
    'first_run' => 'Premier run — pas de données précédentes.',
    'current_data_label' => 'Données actuelles',
    'previous_data_label' => 'Données précédentes',
    'trend.down' => 'Tendance baissière ↓',
    'trend.up' => 'Tendance haussière ↑',
    'trend.mixed' => 'Signal mixte ↕',
    'trend.stable' => 'Stable →',
    'correlation.header' => 'CORRÉLATIONS ENTRE MÉTRIQUES :',
    'correlation.contraction' => 'Contraction globale — les deux métriques baissent ensemble',
    'correlation.growth' => 'Croissance conjointe — les deux métriques montent ensemble',
    'correlation.mix_shift_a' => 'Mix-shift — :metricA s\'érode pendant que :metricB compense',
    'correlation.mix_shift_b' => 'Mix-shift — :metricB s\'érode pendant que :metricA compense',
    'correlation.stable' => 'Stable',
    'correlation.ratio' => '    → Ratio entre métriques : :value',
    'trend.header' => 'TENDANCES SUR PLUSIEURS RUNS :',
    'trend.row' => '  :arrow :col : :slope%/run (consistance :consistency%, total : :total%, sur :runs runs)',
    'prev_context.none' => 'RAPPORT PRÉCÉDENT : Aucun (premier rapport pour ce widget).',
    'prev_data.none' => 'Aucune donnée précédente (premier rapport).',
    'prev_json.none' => 'Aucun',
    'prev_context.full' => 'RAPPORT PRÉCÉDENT :
- Indicateur         : :indicator
- Valeur observée    : :value
- Variation à l\'époque : :variation
- Conclusion         : ":message"
- Généré le          : :date',
    'scores_block' => 'SIGNAUX D\'ÉVOLUTION (calculés algorithmiquement — intégrer dans le rapport) :
- Activité              : :impact/100
- Pression              : :risk/100
- Concentration         : :dep/100
- Instabilité           : :volatility/100
- Divergence            : :corr/100
- Tendance              : :trend/100 — :trend_label

- Niveau d\'alerte       : :global/100
- Priorité              : :priority

Interprétation à inclure :
- Activité ≥ 70  → "forte activité — mouvement significatif"
- Pression ≥ 60  → "pression structurelle significative"
- Concentration ≥ 60 → mentionner la concentration sur quelques entités
- Tendance ↓ ≥ 40 → "dégradation continue sur plusieurs périodes"
- Divergence ≥ 50 → "métriques qui partent en sens opposé — signal d\'alerte"',
    'severity_label.critical' => 'Critique 🔴',
    'severity_label.warning' => 'Attention 🟡',
    'severity_label.info' => 'Informatif 🔵',
    'type_label.anomaly' => 'Anomalie détectée',
    'type_label.trend' => 'Tendance notable',
    'type_label.record' => 'Record détecté',
    'type_label.info' => 'Rapport périodique',
];
