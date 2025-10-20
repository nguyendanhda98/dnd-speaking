# Teacher Session History & Cancel Cleanup Implementation

## Overview
Implemented two features:
1. Teacher session history now shows "Tham gia ngay" button for in-progress sessions
2. Canceling sessions with room links triggers complete cleanup of room data

## Changes Made

### 1. Teacher Session History - Join Button for In-Progress Sessions

**File: `includes/class-rest-api.php` - `get_session_history()` method**

#### Query Update
Changed WHERE clause to include in_progress sessions:
```php
// OLD
$where_clause .= " AND s.status IN ('completed', 'cancelled')";

// NEW
$where_clause .= " AND s.status IN ('in_progress', 'completed', 'cancelled')";
```

#### Display Logic Update
Added status mapping and join button:
```php
// Status mapping with Vietnamese translation
switch ($session->status) {
    case 'in_progress':
        $status_class = 'in_progress';
        $status_text = 'Đang diễn ra';
        break;
    case 'completed':
        $status_class = 'completed';
        $status_text = 'Completed';
        break;
    case 'cancelled':
        $status_class = 'cancelled';
        $status_text = 'Cancelled';
        break;
}

// Show join button for in_progress sessions with room link
if ($session->status === 'in_progress' && !empty($session->discord_channel)) {
    $output .= '<div class="dnd-session-actions">';
    $output .= '<a href="' . esc_url($session->discord_channel) . '" class="dnd-btn dnd-btn-join" target="_blank">Tham gia ngay</a>';
    $output .= '</div>';
}
```

### 2. Cancel Session - Complete Room Cleanup

**File: `includes/class-rest-api.php` - `cancel_session()` method**

#### Enhanced Cancel Flow

```php
// 1. Allow canceling in_progress sessions (not just pending/confirmed)
$session = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $table WHERE id = %d AND student_id = %d AND status IN ('pending', 'confirmed', 'in_progress')",
    $session_id, $user_id
));

// 2. Check if session has room link
$has_room_link = !empty($session->discord_channel);

// 3. If has room link, trigger cleanup
if ($has_room_link) {
    // Send webhook to Discord bot for room cleanup
    wp_remote_post($webhook_url, [
        'body' => json_encode([
            'action' => 'cancel_session',
            'teacher_wp_id' => $teacher_id,
            'student_wp_id' => $user_id,
            'session_id' => $session_id,
            'channelId' => $channel_id,
            'server_id' => get_option('dnd_discord_server_id')
        ]),
        'blocking' => false // Non-blocking request
    ]);

    // Clean up teacher's room metadata
    delete_user_meta($teacher_id, 'discord_voice_channel_id');
    delete_user_meta($teacher_id, 'discord_voice_channel_invite');
    
    // Reset teacher status from busy to online
    $teacher_status = get_user_meta($teacher_id, 'dnd_available', true);
    if ($teacher_status === 'busy') {
        update_user_meta($teacher_id, 'dnd_available', '1');
    }
}
```

## Webhook Integration

### Cancel Session Webhook Payload
```json
{
    "action": "cancel_session",
    "teacher_wp_id": 123,
    "student_wp_id": 456,
    "session_id": 789,
    "channelId": "1234567890",
    "server_id": "0987654321"
}
```

**Purpose**: Notify Discord bot to:
- Remove student from teacher's voice channel
- Clean up any temporary permissions
- Log the cancellation event

## State Management Flow

### When Session is Cancelled with Room Link

1. **Database**: Session status → 'cancelled'
2. **Webhook**: Send cancel_session action to Discord bot (non-blocking)
3. **Teacher Metadata**: 
   - Delete `discord_voice_channel_id`
   - Delete `discord_voice_channel_invite`
4. **Teacher Status**: 
   - If status = 'busy' → Change to '1' (online)
   - Teacher can now accept new students

### Teacher Status State Machine
```
Online ('1') → Busy ('busy') → [Session Cancelled] → Online ('1')
                              ↓
                       [Teacher goes offline] → Offline ('0')
```

## UI/UX Impact

### Teacher Session History Page
- **Before**: Only showed completed and cancelled sessions
- **After**: Shows in-progress sessions with blue "Tham gia ngay" button

### Cancel Session Behavior
- **Before**: Only updated session status
- **After**: Complete cleanup flow
  1. Updates session status
  2. Sends webhook to Discord
  3. Clears room metadata
  4. Resets teacher to available

## Database Columns Used
- `wp_dnd_speaking_sessions.discord_channel` - Room link
- `wp_dnd_speaking_sessions.status` - Session status
- `wp_usermeta.discord_voice_channel_id` - Teacher's channel ID
- `wp_usermeta.discord_voice_channel_invite` - Invite link
- `wp_usermeta.dnd_available` - Teacher availability status

## Error Handling
- Non-blocking webhook call (doesn't wait for Discord response)
- Graceful degradation if webhook fails
- Metadata cleanup happens regardless of webhook status

## Testing Checklist
- [ ] Teacher can see in-progress sessions in history
- [ ] Join button appears for in-progress sessions
- [ ] Click join button opens Discord channel
- [ ] Student cancels session → Teacher room data cleaned
- [ ] Teacher cancels session → Room data cleaned
- [ ] Teacher status changes from busy to online after cancel
- [ ] Webhook payload sent correctly
- [ ] Works when student cancels
- [ ] Works when teacher cancels (if implemented)

## Future Enhancements
- [ ] Add teacher-side cancel session endpoint
- [ ] Add confirmation dialog before cancel
- [ ] Show notification to teacher when session cancelled
- [ ] Track cancellation reasons
- [ ] Implement refund logic for cancelled sessions
