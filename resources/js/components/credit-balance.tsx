import { Button } from '@/components/ui/button';
import { usePage } from '@inertiajs/react';
import { Coins, Plus } from 'lucide-react';
import { router } from '@inertiajs/react';
import { useFormatCurrency } from '@/hooks/use-currency';

interface Role {
    slug: string;
}

interface AuthUser {
    credit_balance?: number | string;
    roles?: Role[];
}

interface PageProps extends Record<string, unknown> {
    auth: {
        user: AuthUser;
    };
}

export function CreditBalance() {
    const { auth } = usePage<PageProps>().props;
    
    // Only show for customers
    const isCustomer = auth.user?.roles?.some((role) => role.slug === 'customer');
    
    if (!isCustomer) {
        return null;
    }

    const { formatCurrency } = useFormatCurrency();

    // Convert credit_balance to a number safely
    const creditBalance = auth.user?.credit_balance;
    const credits = typeof creditBalance === 'number'
        ? creditBalance
        : parseFloat(creditBalance as string) || 0;
    const isLowCredits = credits < 10;

    const handleTopUp = () => {
        router.visit('/credit/top-up');
    };

    return (
        <div className="flex items-center gap-2">
            <Button
                variant="outline"
                className="h-9 gap-2 md:h-10"
                onClick={handleTopUp}
            >
                <Coins className={`h-4 w-4 ${isLowCredits ? 'text-yellow-600' : 'text-green-600'}`} />
                <div className="hidden items-center gap-1 md:flex">
                    <span className="text-sm font-semibold">{formatCurrency(credits)}</span>
                    <span className="text-xs text-muted-foreground">credits</span>
                </div>
                <span className="text-sm font-semibold md:hidden">{formatCurrency(credits)}</span>
            </Button>
            
            <Button
                size="icon"
                variant="default"
                className="h-9 w-9 md:h-10 md:w-10"
                onClick={handleTopUp}
                title="Top up credits"
            >
                <Plus className="h-4 w-4" />
            </Button>
        </div>
    );
}
