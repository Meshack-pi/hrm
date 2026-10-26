import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function usePermissions() {
    const page = usePage();

    const permissions = computed(() => page.props.auth?.permissions ?? []);
    const roles = computed(() => page.props.auth?.roles ?? []);

    function can(permission) {
        return permissions.value.includes(permission);
    }

    function hasRole(role) {
        return roles.value.includes(role);
    }

    function hasAnyRole(...roleList) {
        return roleList.some(role => roles.value.includes(role));
    }

    function canAny(...permissionList) {
        return permissionList.some(p => permissions.value.includes(p));
    }

    return { can, hasRole, hasAnyRole, canAny, permissions, roles };
}
