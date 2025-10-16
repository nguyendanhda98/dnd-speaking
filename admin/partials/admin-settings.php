<div class="wrap">
    <h1>DND Speaking Settings</h1>
    <form method="post" action="options.php">
        <?php
        settings_fields('dnd_speaking_settings');
        do_settings_sections('dnd_speaking_settings');
        submit_button();
        ?>
    </form>
</div>
