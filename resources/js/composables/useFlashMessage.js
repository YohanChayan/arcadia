import { watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { toast } from 'vue-sonner';

export function useFlashMessage() {
    const page = usePage();

    const checkFlashMessages = (message) => {
        if (message?.error && message.error !== '') {
            toast.error(message.error, {
                duration: 5000,
                position: 'top-right',
            });
        }
        
        if (message?.success && message.success !== '') {
            toast.success(message.success, {
                duration: 5000,
                position: 'top-right',
            });
        }
    };

    // Watch for changes in flash messages (handles page navigations)
    watch(
        () => page.props.message,
        (newMessage) => {
            if (newMessage) {
                checkFlashMessages(newMessage);
            }
        },
        { immediate: true, deep: true }
    );

    return {
        checkFlashMessages
    };
}
