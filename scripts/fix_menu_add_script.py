from pathlib import Path
path = Path(r'c:\xampp\htdocs\casa_gunita\admin\menu_add.php')
text = path.read_text(encoding='utf-8')
start = text.find('<script>')
end = text.find('</script>', start)
if start == -1 or end == -1:
    raise RuntimeError('Script block not found')
end += len('</script>')
new_script = '''<script>
document.addEventListener('DOMContentLoaded', function () {
    function updateRequiredState() {
        document.querySelectorAll('input[name="modifier_group_ids[]"]').forEach(function (checkbox) {
            const requiredCheckbox = document.querySelector('input[name="required_modifier_group_ids[]"][value="' + checkbox.value + '"]');
            if (!requiredCheckbox) return;
            requiredCheckbox.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                requiredCheckbox.checked = false;
            }
        });
    }

    document.querySelectorAll('input[name="modifier_group_ids[]"]').forEach(function (checkbox) {
        checkbox.addEventListener('change', updateRequiredState);
    });

    updateRequiredState();
});
</script>'''
path.write_text(text[:start] + new_script + text[end:], encoding='utf-8')
print('Replaced script block in menu_add.php')
