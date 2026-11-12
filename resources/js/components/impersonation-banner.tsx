import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { usePage, router } from '@inertiajs/react';
import { UserX, AlertCircle } from 'lucide-react';

export function ImpersonationBanner() {
    const { auth } = usePage().props as { 
        auth: { 
            isImpersonating: boolean;
            user: {
                name: string;
                email: string;
            }
        } 
    };

    if (!auth.isImpersonating) {
        return null;
    }

    const handleLeaveImpersonate = () => {
        router.post('/leave-impersonate');
    };

    return (
        <Alert className="mb-4 border-orange-500 bg-orange-50 text-orange-900">
            <AlertCircle className="h-4 w-4 !text-orange-600" />
            <AlertDescription className="flex items-center justify-between">
                <div className="flex items-center gap-2">
                    <span className="font-semibold">Impersonating:</span>
                    <span>{auth.user.name}</span>
                    <span className="text-sm text-orange-700">({auth.user.email})</span>
                </div>
                <Button
                    onClick={handleLeaveImpersonate}
                    variant="outline"
                    size="sm"
                    className="border-orange-600 text-orange-900 hover:bg-orange-100"
                >
                    <UserX className="mr-2 h-4 w-4" />
                    Leave Impersonation
                </Button>
            </AlertDescription>
        </Alert>
    );
}
