package com.elkuku.agenttools.ui

import android.graphics.BitmapFactory
import android.util.Base64
import androidx.annotation.DrawableRes
import androidx.compose.foundation.Image
import androidx.compose.foundation.layout.Arrangement
import androidx.compose.foundation.layout.Box
import androidx.compose.foundation.layout.Column
import androidx.compose.foundation.layout.Row
import androidx.compose.foundation.layout.padding
import androidx.compose.foundation.layout.size
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.material3.DropdownMenu
import androidx.compose.material3.DropdownMenuItem
import androidx.compose.material3.HorizontalDivider
import androidx.compose.material3.Icon
import androidx.compose.material3.IconButton
import androidx.compose.material3.MaterialTheme
import androidx.compose.material3.Text
import androidx.compose.runtime.Composable
import androidx.compose.runtime.getValue
import androidx.compose.runtime.mutableStateOf
import androidx.compose.runtime.remember
import androidx.compose.runtime.setValue
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.ImageBitmap
import androidx.compose.ui.graphics.asImageBitmap
import androidx.compose.ui.layout.ContentScale
import androidx.compose.ui.res.painterResource
import androidx.compose.ui.res.stringResource
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.Dp
import androidx.compose.ui.unit.dp
import com.elkuku.agenttools.R
import com.elkuku.agenttools.data.UserRole

/**
 * The signed-in user's menu, like web's: the profile picture (or a person icon) opens the account
 * with its role, the user's stats and banners, the settings and signing out.
 */
@Composable
fun AccountMenu(
    email: String,
    role: UserRole?,
    avatar: String?,
    onOpenStats: () -> Unit,
    onOpenBanners: () -> Unit,
    onOpenSettings: () -> Unit,
    onSignOut: () -> Unit,
    enabled: Boolean,
) {
    var expanded by remember { mutableStateOf(false) }
    val picture = rememberAvatar(avatar)

    Box {
        IconButton(onClick = { expanded = true }) {
            Avatar(picture, size = 32.dp, description = stringResource(R.string.account))
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            Row(
                verticalAlignment = Alignment.CenterVertically,
                horizontalArrangement = Arrangement.spacedBy(12.dp),
                modifier = Modifier.padding(horizontal = 16.dp, vertical = 8.dp),
            ) {
                Avatar(picture, size = 40.dp, description = null)
                Column {
                    Text(email, style = MaterialTheme.typography.bodyMedium, maxLines = 1, overflow = TextOverflow.Ellipsis)
                    role?.let { RoleBadge(it) }
                }
            }
            HorizontalDivider()
            MenuItem(R.drawable.ic_bi_bar_chart, stringResource(R.string.my_stats)) {
                expanded = false
                onOpenStats()
            }
            MenuItem(R.drawable.ic_bi_grid_3x3_gap, stringResource(R.string.my_banners)) {
                expanded = false
                onOpenBanners()
            }
            MenuItem(R.drawable.ic_bi_gear, stringResource(R.string.settings)) {
                expanded = false
                onOpenSettings()
            }
            HorizontalDivider()
            MenuItem(R.drawable.ic_bi_box_arrow_right, stringResource(R.string.sign_out), enabled) {
                expanded = false
                onSignOut()
            }
        }
    }
}

@Composable
internal fun MenuItem(@DrawableRes icon: Int, text: String, enabled: Boolean = true, onClick: () -> Unit) {
    DropdownMenuItem(
        text = { Text(text) },
        onClick = onClick,
        enabled = enabled,
        leadingIcon = { Icon(painterResource(icon), contentDescription = null, modifier = Modifier.size(20.dp)) },
    )
}

/** The picture in a circle, or the person icon without one. */
@Composable
internal fun Avatar(picture: ImageBitmap?, size: Dp, description: String?) {
    if (picture != null) {
        Image(
            picture,
            contentDescription = description,
            contentScale = ContentScale.Crop,
            modifier = Modifier.size(size).clip(CircleShape),
        )
    } else {
        Icon(painterResource(R.drawable.ic_bi_person), contentDescription = description, modifier = Modifier.size(size * 0.6f))
    }
}

/** Decodes web's `data:image/jpeg;base64,…` picture; null without one or when it is not an image. */
@Composable
internal fun rememberAvatar(avatar: String?): ImageBitmap? = remember(avatar) {
    avatar?.substringAfter("base64,", "")?.takeIf { it.isNotEmpty() }?.let { base64 ->
        runCatching {
            val bytes = Base64.decode(base64, Base64.DEFAULT)
            BitmapFactory.decodeByteArray(bytes, 0, bytes.size)?.asImageBitmap()
        }.getOrNull()
    }
}

/**
 * The app's features, next to the account menu: the agents, for admins ([onOpenAdminAgents]) every
 * user with the privacy settings, and what comes next.
 */
@Composable
fun FeatureMenu(onOpenAgents: () -> Unit, onOpenAdminAgents: (() -> Unit)? = null) {
    var expanded by remember { mutableStateOf(false) }

    Box {
        IconButton(onClick = { expanded = true }) {
            Icon(painterResource(R.drawable.ic_bi_list), contentDescription = stringResource(R.string.menu), modifier = Modifier.size(24.dp))
        }
        DropdownMenu(expanded = expanded, onDismissRequest = { expanded = false }) {
            MenuItem(R.drawable.ic_bi_people, stringResource(R.string.agents)) {
                expanded = false
                onOpenAgents()
            }
            onOpenAdminAgents?.let { open ->
                MenuItem(R.drawable.ic_bi_person_gear, stringResource(R.string.admin_agents)) {
                    expanded = false
                    open()
                }
            }
        }
    }
}
