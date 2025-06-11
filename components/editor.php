<?php
$editor_id = $editor_id ?? 'editor_' . uniqid();
$editor_name = $editor_name ?? $editor_id;
$editor_value = $editor_value ?? '';
?>

<textarea id="<?= $editor_id ?>" name="<?= $editor_name ?>" class="w-full p-2 border rounded bg-white text-sm">
<?= htmlspecialchars($editor_value) ?>
</textarea>

<script src="https://cdn.jsdelivr.net/npm/tinymce@6.8.3/tinymce.min.js" referrerpolicy="origin"></script>
<script>
tinymce.init({
    selector: '#<?= $editor_id ?>',
    height: 300,
    menubar: false,
    plugins: 'lists link',
    toolbar: 'undo redo | bold italic underline | fontsize forecolor backcolor | bullist numlist | link',
    branding: false,
    content_style: 'body { font-family: Inter, sans-serif; font-size: 14px; }'
});
</script>
