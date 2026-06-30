<style>
    .hint { display:block; margin-top:6px; color:#94a3b8; }
    .permissions-panel { margin-top: 24px; border: 1px solid rgba(148, 163, 184, .28); border-radius: 16px; padding: 18px; background: rgba(15, 23, 42, .32); }
    .permissions-header { display: flex; align-items: center; gap: 10px; justify-content: space-between; flex-wrap: wrap; margin-bottom: 16px; }
    .permissions-header h3 { margin: 0 0 4px; font-size: 18px; color: #f8fafc; }
    .permissions-header p { margin: 0; color: #94a3b8; }
    .permissions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(230px, 1fr)); gap: 14px; }
    .permission-group { background: rgba(15, 23, 42, .62); border: 1px solid rgba(148, 163, 184, .22); border-radius: 14px; padding: 14px; }
    .permission-group strong { display: block; margin-bottom: 10px; color: #e5e7eb; }
    .permission-item { display: flex; gap: 8px; align-items: center; margin: 8px 0; color: #cbd5e1; }
    .permission-item input { width: auto; }
</style>

<script>
    function toggleAllPermissions(checked) {
        document.querySelectorAll('input[name="permissions[]"]').forEach((checkbox) => checkbox.checked = checked);
    }

    document.addEventListener('DOMContentLoaded', () => {
        const roleSelect = document.getElementById('roleSelect');
        const panel = document.getElementById('permissionsPanel');
        if (!roleSelect || !panel) return;

        const updatePanel = () => {
            const isAdmin = roleSelect.value === 'admin';
            panel.style.opacity = isAdmin ? '.55' : '1';
            panel.querySelectorAll('input[name="permissions[]"]').forEach((checkbox) => {
                checkbox.disabled = isAdmin;
            });
        };

        roleSelect.addEventListener('change', updatePanel);
        updatePanel();
    });
</script>