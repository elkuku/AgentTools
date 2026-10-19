package com.elkuku.uploadapp.ui

import androidx.annotation.DrawableRes
import com.elkuku.uploadapp.R

/**
 * Badge images (from 4e-website) by agent_stat column, for bronze to onyx.
 * The file names follow MedalChecker::getBadgePath, e.g. recon → badge_opr_*, ifs → badge_fs_*.
 * Referenced directly so resource shrinking keeps them.
 */
val MEDAL_BADGES: Map<String, List<Int>> = mapOf(
    "explorer" to listOf(
        R.drawable.badge_explorer_bronze, R.drawable.badge_explorer_silver, R.drawable.badge_explorer_gold, R.drawable.badge_explorer_platinum, R.drawable.badge_explorer_black,
    ),
    "seer" to listOf(
        R.drawable.badge_seer_bronze, R.drawable.badge_seer_silver, R.drawable.badge_seer_gold, R.drawable.badge_seer_platinum, R.drawable.badge_seer_black,
    ),
    "recon" to listOf(
        R.drawable.badge_opr_bronze, R.drawable.badge_opr_silver, R.drawable.badge_opr_gold, R.drawable.badge_opr_platinum, R.drawable.badge_opr_black,
    ),
    "trekker" to listOf(
        R.drawable.badge_trekker_bronze, R.drawable.badge_trekker_silver, R.drawable.badge_trekker_gold, R.drawable.badge_trekker_platinum, R.drawable.badge_trekker_black,
    ),
    "builder" to listOf(
        R.drawable.badge_builder_bronze, R.drawable.badge_builder_silver, R.drawable.badge_builder_gold, R.drawable.badge_builder_platinum, R.drawable.badge_builder_black,
    ),
    "connector" to listOf(
        R.drawable.badge_connector_bronze, R.drawable.badge_connector_silver, R.drawable.badge_connector_gold, R.drawable.badge_connector_platinum, R.drawable.badge_connector_black,
    ),
    "mind_controller" to listOf(
        R.drawable.badge_mind_controller_bronze, R.drawable.badge_mind_controller_silver, R.drawable.badge_mind_controller_gold, R.drawable.badge_mind_controller_platinum, R.drawable.badge_mind_controller_black,
    ),
    "engineer" to listOf(
        R.drawable.badge_engineer_bronze, R.drawable.badge_engineer_silver, R.drawable.badge_engineer_gold, R.drawable.badge_engineer_platinum, R.drawable.badge_engineer_black,
    ),
    "illuminator" to listOf(
        R.drawable.badge_illuminator_bronze, R.drawable.badge_illuminator_silver, R.drawable.badge_illuminator_gold, R.drawable.badge_illuminator_platinum, R.drawable.badge_illuminator_black,
    ),
    "recharger" to listOf(
        R.drawable.badge_recharger_bronze, R.drawable.badge_recharger_silver, R.drawable.badge_recharger_gold, R.drawable.badge_recharger_platinum, R.drawable.badge_recharger_black,
    ),
    "liberator" to listOf(
        R.drawable.badge_liberator_bronze, R.drawable.badge_liberator_silver, R.drawable.badge_liberator_gold, R.drawable.badge_liberator_platinum, R.drawable.badge_liberator_black,
    ),
    "pioneer" to listOf(
        R.drawable.badge_pioneer_bronze, R.drawable.badge_pioneer_silver, R.drawable.badge_pioneer_gold, R.drawable.badge_pioneer_platinum, R.drawable.badge_pioneer_black,
    ),
    "purifier" to listOf(
        R.drawable.badge_purifier_bronze, R.drawable.badge_purifier_silver, R.drawable.badge_purifier_gold, R.drawable.badge_purifier_platinum, R.drawable.badge_purifier_black,
    ),
    "specops" to listOf(
        R.drawable.badge_spec_ops_bronze, R.drawable.badge_spec_ops_silver, R.drawable.badge_spec_ops_gold, R.drawable.badge_spec_ops_platinum, R.drawable.badge_spec_ops_black,
    ),
    "missionday" to listOf(
        R.drawable.badge_mission_day_prime_bronze, R.drawable.badge_mission_day_prime_silver, R.drawable.badge_mission_day_prime_gold, R.drawable.badge_mission_day_prime_platinum, R.drawable.badge_mission_day_prime_black,
    ),
    "nl1331_meetups" to listOf(
        R.drawable.badge_nl1331_bronze, R.drawable.badge_nl1331_silver, R.drawable.badge_nl1331_gold, R.drawable.badge_nl1331_platinum, R.drawable.badge_nl1331_black,
    ),
    "hacker" to listOf(
        R.drawable.badge_hacker_bronze, R.drawable.badge_hacker_silver, R.drawable.badge_hacker_gold, R.drawable.badge_hacker_platinum, R.drawable.badge_hacker_black,
    ),
    "translator" to listOf(
        R.drawable.badge_translator_bronze, R.drawable.badge_translator_silver, R.drawable.badge_translator_gold, R.drawable.badge_translator_platinum, R.drawable.badge_translator_black,
    ),
    "sojourner" to listOf(
        R.drawable.badge_sojourner_bronze, R.drawable.badge_sojourner_silver, R.drawable.badge_sojourner_gold, R.drawable.badge_sojourner_platinum, R.drawable.badge_sojourner_black,
    ),
    "epoch" to listOf(
        R.drawable.badge_epoch_bronze, R.drawable.badge_epoch_silver, R.drawable.badge_epoch_gold, R.drawable.badge_epoch_platinum, R.drawable.badge_epoch_black,
    ),
    "recruiter" to listOf(
        R.drawable.badge_recruiter_bronze, R.drawable.badge_recruiter_silver, R.drawable.badge_recruiter_gold, R.drawable.badge_recruiter_platinum, R.drawable.badge_recruiter_black,
    ),
    "ifs" to listOf(
        R.drawable.badge_fs_bronze, R.drawable.badge_fs_silver, R.drawable.badge_fs_gold, R.drawable.badge_fs_platinum, R.drawable.badge_fs_black,
    ),
    "second_sunday" to listOf(
        R.drawable.badge_second_sunday_bronze, R.drawable.badge_second_sunday_silver, R.drawable.badge_second_sunday_gold, R.drawable.badge_second_sunday_platinum, R.drawable.badge_second_sunday_black,
    ),
    "scout" to listOf(
        R.drawable.badge_scout_bronze, R.drawable.badge_scout_silver, R.drawable.badge_scout_gold, R.drawable.badge_scout_platinum, R.drawable.badge_scout_black,
    ),
    "scout_controller" to listOf(
        R.drawable.badge_scout_controller_bronze, R.drawable.badge_scout_controller_silver, R.drawable.badge_scout_controller_gold, R.drawable.badge_scout_controller_platinum, R.drawable.badge_scout_controller_black,
    ),
    "maverick" to listOf(
        R.drawable.badge_maverick_bronze, R.drawable.badge_maverick_silver, R.drawable.badge_maverick_gold, R.drawable.badge_maverick_platinum, R.drawable.badge_maverick_black,
    ),
    "reclaimer" to listOf(
        R.drawable.badge_reclaimer_bronze, R.drawable.badge_reclaimer_silver, R.drawable.badge_reclaimer_gold, R.drawable.badge_reclaimer_platinum, R.drawable.badge_reclaimer_black,
    ),
)

@DrawableRes
fun medalBadge(column: String, level: Int): Int? = MEDAL_BADGES[column]?.getOrNull(level - 1)

/** C.O.R.E badges: the plain one, then 24, 36, 48 and 60 months (see CORE_BADGE_MONTHS). */
val CORE_BADGES = listOf(
    R.drawable.unique_badge_core,
    R.drawable.unique_badge_dual_core,
    R.drawable.unique_badge_core_year3,
    R.drawable.unique_badge_quad_core,
    R.drawable.unique_badge_penta_core,
)
