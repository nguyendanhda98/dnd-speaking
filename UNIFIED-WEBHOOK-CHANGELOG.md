# Unified Webhook Implementation - Changelog

**Date:** October 20, 2025

## Overview

Thay đổi từ việc sử dụng 2 webhook riêng biệt sang 1 webhook duy nhất cho tất cả các Discord integration. Webhook sẽ phân biệt các action khác nhau thông qua field `action` trong request body.

## Thay đổi

### 1. Settings Configuration

**Trước:**
- `dnd_teacher_status_webhook` - Webhook cho teacher status (online/offline)
- `dnd_student_start_now_webhook` - Webhook cho student start now

**Sau:**
- `dnd_discord_webhook` - Webhook duy nhất cho tất cả Discord integrations

### 2. File Changes

#### `includes/class-admin.php`

**UI Changes (Webhook Integration Tab):**
- Thay 2 input field thành 1 input field duy nhất
- Label: "Discord Webhook URL"
- Description: "Webhook URL for all Discord integrations. The system will send different 'action' values: 'online', 'offline', 'student_start_now'."

**Register Settings:**
```php
// Removed
register_setting('dnd_speaking_discord_settings', 'dnd_teacher_status_webhook');
register_setting('dnd_speaking_discord_settings', 'dnd_student_start_now_webhook');

// Added
register_setting('dnd_speaking_discord_settings', 'dnd_discord_webhook');
```

**Hidden Fields:**
- Updated all tabs to preserve `dnd_discord_webhook` instead of 2 separate webhooks

**handle_teacher_availability() Method:**
```php
// Changed from:
$webhook_url = get_option('dnd_teacher_status_webhook');

// To:
$webhook_url = get_option('dnd_discord_webhook');
```

- Moved `'action'` field to first position in request body for better clarity
- Both online and offline actions use the same webhook URL

#### `includes/class-rest-api.php`

**student_start_now() Method:**
```php
// Changed from:
$webhook_url = get_option('dnd_student_start_now_webhook');

// To:
$webhook_url = get_option('dnd_discord_webhook');
```

### 3. Webhook Request Format

All webhook requests now go to the same URL with different `action` values:

#### Action: "online" (Teacher goes online)
```json
{
  "action": "online",
  "discord_user_id": "123456789",
  "discord_global_name": "TeacherName",
  "server_id": "111222333"
}
```

**Response:**
```json
{
  "channelId": "987654321"
}
```

#### Action: "offline" (Teacher goes offline)
```json
{
  "action": "offline",
  "discord_user_id": "123456789",
  "discord_global_name": "TeacherName",
  "server_id": "111222333",
  "channelId": "987654321"
}
```

#### Action: "student_start_now" (Student starts session)
```json
{
  "action": "student_start_now",
  "student_discord_id": "123456789",
  "student_discord_name": "StudentName",
  "student_wp_id": 123,
  "teacher_wp_id": 456,
  "teacher_room_id": "987654321",
  "server_id": "111222333"
}
```

**Response:**
```json
{
  "success": true,
  "room_id": "987654321",
  "room_link": "https://discord.com/channels/111222333/987654321"
}
```

## Migration Notes

### For Administrators

1. **Backup current settings:**
   - Note down the current values of both webhooks if they're different
   
2. **Update webhook URL:**
   - Go to Discord Settings > Webhook Integration
   - Enter the unified webhook URL in the "Discord Webhook URL" field
   - Save settings

3. **Verify webhook server:**
   - Ensure your webhook server can handle all three action types
   - Test each action:
     - Teacher going online
     - Teacher going offline
     - Student starting a session

### For Webhook Server Developers

The webhook server must now:

1. **Accept a single endpoint for all actions**
   - Check the `action` field in request body
   - Route to appropriate handler based on action value

2. **Handle three action types:**
   - `"online"` - Create voice channel for teacher
   - `"offline"` - Delete voice channel for teacher
   - `"student_start_now"` - Add student to teacher's voice channel

3. **Return appropriate response format:**
   - For "online": `{"channelId": "..."}`
   - For "offline": No specific response needed
   - For "student_start_now": `{"success": true, "room_id": "...", "room_link": "..."}`

## Benefits

1. **Simplified Configuration:** Only one webhook URL to manage
2. **Easier Maintenance:** Centralized webhook handling
3. **Consistent Error Handling:** All actions use same error handling logic
4. **Flexible Routing:** Server can route actions based on `action` field
5. **Easier Testing:** Single endpoint to test and monitor

## Backward Compatibility

**Note:** This is a breaking change. The old webhook settings will no longer be used:
- `dnd_teacher_status_webhook` (deprecated)
- `dnd_student_start_now_webhook` (deprecated)

Administrators must update to the new `dnd_discord_webhook` setting.

## Testing Checklist

- [x] UI displays single webhook field correctly
- [x] Settings are saved and retrieved properly
- [x] Hidden fields preserve webhook across tabs
- [x] Teacher online action sends correct format with action="online"
- [x] Teacher offline action sends correct format with action="offline"
- [x] Student start now sends correct format with action="student_start_now"
- [ ] Webhook server handles all three actions correctly
- [ ] Error messages are appropriate for each action
- [ ] Documentation is updated

## Files Modified

1. `includes/class-admin.php` - Settings UI and teacher availability handler
2. `includes/class-rest-api.php` - Student start now handler
3. `STUDENT-START-NOW-IMPLEMENTATION.md` - Updated documentation

## Files Created

1. `UNIFIED-WEBHOOK-CHANGELOG.md` - This file
