import { BreadcrumbItem, UserKycVerification } from '@/types';
import { useTranslation } from 'react-i18next';
import { Head, useForm } from '@inertiajs/react';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import HeadingSmall from '@/components/heading-small';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Building, ChevronLeft, ChevronRight, Upload, FileText, Camera, Info } from 'lucide-react';
import { Link } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { useState } from 'react';
import { cn } from '@/lib/utils';

interface Props {
    kyc: UserKycVerification;
}

const steps = [
    { id: 1, name: 'Business Info', icon: Building },
    { id: 2, name: 'Documents', icon: FileText },
    { id: 3, name: 'Selfie', icon: Camera },
];

export default function KycBusiness({ kyc }: Props) {
  const { t } = useTranslation();
  const breadcrumbs: BreadcrumbItem[] = [
    {
        title: t('settings.title'), href: '/settings/profile',
    },
    {
        title: t('settings.kyc_verification'), href: '/settings/kyc',
    },
    {
        title: t('settings.business_verification'), href: '/settings/kyc/business',
    },
];
    const [currentStep, setCurrentStep] = useState(1);
    const { data, setData, post, processing, errors } = useForm({
        business_name: kyc?.business_name || '',
        business_registration_number: kyc?.business_registration_number || '',
        business_type: kyc?.business_type || '',
        business_address_line1: kyc?.business_address_line1 || '',
        business_address_line2: kyc?.business_address_line2 || '',
        business_city: kyc?.business_city || '',
        business_state: kyc?.business_state || '',
        business_postal_code: kyc?.business_postal_code || '',
        business_country: kyc?.business_country || 'US',
        id_document_type: kyc?.id_document_type || '',
        id_document: null as File | null,
        business_document: null as File | null,
        selfie_with_id: null as File | null,
    });

    const [previews, setPreviews] = useState<{
        id_document?: string;
        business_document?: string;
        selfie_with_id?: string;
    }>({});

    const handleFileChange = (field: 'id_document' | 'business_document' | 'selfie_with_id', file: File | null) => {
        if (file) {
            setData(field, file);
            const reader = new FileReader();
            reader.onloadend = () => {
                setPreviews(prev => ({ ...prev, [field]: reader.result as string }));
            };
            reader.readAsDataURL(file);
        } else {
            setData(field, null);
            setPreviews(prev => ({ ...prev, [field]: undefined }));
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/settings/kyc/business');
    };

    const canGoNext = () => {
        if (currentStep === 1) {
            return data.business_name && data.business_registration_number && data.business_type &&
                   data.business_address_line1 && data.business_city && data.business_state &&
                   data.business_postal_code && data.business_country;
        }
        if (currentStep === 2) {
            return data.id_document_type && data.id_document && data.business_document;
        }
        return false;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={t('settings.business_verification_kyc')} />

            <SettingsLayout>
                <div className="space-y-6 p-6">
                    {/* Header */}
                    <div className="flex items-start justify-between">
                        <div>
                            <Button asChild variant="ghost" size="sm" className="mb-3 -ml-2">
                                <Link href="/settings/kyc">
                                    <ChevronLeft className="mr-1 h-4 w-4" />
                                    Back to KYC Status
                                </Link>
                            </Button>
                            <HeadingSmall
                                title={t('settings.business_verification')}
                                description={t('settings.complete_your_business_information_for_full_kyc_ve')}
                            />
                        </div>
                    </div>

                    {/* Info Alert */}
                <Alert className="border-purple-200 bg-purple-50 dark:border-purple-900/50 dark:bg-purple-900/20">
                    <Info className="h-4 w-4 text-purple-600 dark:text-purple-400" />
                    <AlertTitle className="text-purple-900 dark:text-purple-100">Business Information</AlertTitle>
                    <AlertDescription className="text-purple-800 dark:text-purple-200">
                        <p className="text-sm">
                            Provide your business information to complete the verification process.
                        </p>
                    </AlertDescription>
                </Alert>                    {/* Progress Stepper */}
                    <Card>
                        <CardContent className="p-6">
                            <div className="flex items-center justify-between">
                                {steps.map((step, index) => {
                                    const StepIcon = step.icon;
                                    const isActive = currentStep === step.id;
                                    const isComplete = currentStep > step.id;
                                    
                                    return (
                                        <div key={step.id} className="flex flex-1 items-center">
                                            <div className="flex flex-col items-center">
                                                <div
                                                    className={cn(
                                                        'flex h-10 w-10 items-center justify-center rounded-full border-2 transition-colors',
                                                        isActive && 'border-primary bg-primary text-primary-foreground',
                                                        isComplete && 'border-green-600 bg-green-600 text-white dark:border-green-500 dark:bg-green-500',
                                                        !isActive && !isComplete && 'border-muted bg-background'
                                                    )}
                                                >
                                                    <StepIcon className="h-5 w-5" />
                                                </div>
                                                <span
                                                    className={cn(
                                                        'mt-2 text-xs font-medium',
                                                        isActive && 'text-foreground',
                                                        !isActive && 'text-muted-foreground'
                                                    )}
                                                >
                                                    {step.name}
                                                </span>
                                            </div>
                                            {index < steps.length - 1 && (
                                                <div
                                                    className={cn(
                                                        'mx-2 h-0.5 flex-1 transition-colors',
                                                        isComplete ? 'bg-green-600 dark:bg-green-500' : 'bg-muted'
                                                    )}
                                                />
                                            )}
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>

                    {/* Form */}
                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Step 1: Business Information */}
                        {currentStep === 1 && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-lg">{t('settings.business_information')}</CardTitle>
                                    <CardDescription>
                                        Provide your company details
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor="business_name">
                                                Business Name <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="business_name"
                                                value={data.business_name}
                                                onChange={(e) => setData('business_name', e.target.value)}
                                                placeholder="Acme Corporation"
                                                required
                                            />
                                            <InputError message={errors.business_name} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="business_registration_number">
                                                Registration Number <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="business_registration_number"
                                                value={data.business_registration_number}
                                                onChange={(e) => setData('business_registration_number', e.target.value)}
                                                placeholder="123456789"
                                                required
                                            />
                                            <InputError message={errors.business_registration_number} />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="business_type">
                                            Business Type <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={data.business_type}
                                            onValueChange={(value) => setData('business_type', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select business type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="sole_proprietorship">Sole Proprietorship</SelectItem>
                                                <SelectItem value="partnership">Partnership</SelectItem>
                                                <SelectItem value="llc">LLC</SelectItem>
                                                <SelectItem value="corporation">Corporation</SelectItem>
                                                <SelectItem value="other">Other</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.business_type} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="business_address_line1">
                                            Address Line 1 <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            id="business_address_line1"
                                            value={data.business_address_line1}
                                            onChange={(e) => setData('business_address_line1', e.target.value)}
                                            placeholder="123 Main Street"
                                            required
                                        />
                                        <InputError message={errors.business_address_line1} />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="business_address_line2">Address Line 2</Label>
                                        <Input
                                            id="business_address_line2"
                                            value={data.business_address_line2}
                                            onChange={(e) => setData('business_address_line2', e.target.value)}
                                            placeholder="Suite 100"
                                        />
                                        <InputError message={errors.business_address_line2} />
                                    </div>

                                    <div className="grid gap-4 sm:grid-cols-3">
                                        <div className="space-y-2">
                                            <Label htmlFor="business_city">
                                                City <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="business_city"
                                                value={data.business_city}
                                                onChange={(e) => setData('business_city', e.target.value)}
                                                placeholder="New York"
                                                required
                                            />
                                            <InputError message={errors.business_city} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="business_state">
                                                State/Province <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="business_state"
                                                value={data.business_state}
                                                onChange={(e) => setData('business_state', e.target.value)}
                                                placeholder="NY"
                                                required
                                            />
                                            <InputError message={errors.business_state} />
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="business_postal_code">
                                                Postal Code <span className="text-destructive">*</span>
                                            </Label>
                                            <Input
                                                id="business_postal_code"
                                                value={data.business_postal_code}
                                                onChange={(e) => setData('business_postal_code', e.target.value)}
                                                placeholder="10001"
                                                required
                                            />
                                            <InputError message={errors.business_postal_code} />
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="business_country">
                                            Country <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={data.business_country}
                                            onValueChange={(value) => setData('business_country', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="US">United States</SelectItem>
                                                <SelectItem value="CA">Canada</SelectItem>
                                                <SelectItem value="GB">United Kingdom</SelectItem>
                                                <SelectItem value="AU">Australia</SelectItem>
                                                <SelectItem value="IN">India</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.business_country} />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Step 2: Documents */}
                        {currentStep === 2 && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-lg">{t('settings.upload_documents')}</CardTitle>
                                    <CardDescription>
                                        Provide clear copies of your identification and business documents
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* ID Document Type */}
                                    <div className="space-y-2">
                                        <Label htmlFor="id_document_type">
                                            ID Document Type <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={data.id_document_type}
                                            onValueChange={(value) => setData('id_document_type', value)}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select ID type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="passport">Passport</SelectItem>
                                                <SelectItem value="drivers_license">Driver's License</SelectItem>
                                                <SelectItem value="national_id">National ID Card</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError message={errors.id_document_type} />
                                    </div>

                                    {/* ID Document Upload */}
                                    <div className="space-y-2">
                                        <Label htmlFor="id_document">
                                            ID Document <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="flex items-center gap-4">
                                            <Input
                                                id="id_document"
                                                type="file"
                                                accept="image/jpeg,image/png,application/pdf"
                                                onChange={(e) => handleFileChange('id_document', e.target.files?.[0] || null)}
                                                className="hidden"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => document.getElementById('id_document')?.click()}
                                                className="w-full"
                                            >
                                                <Upload className="mr-2 h-4 w-4" />
                                                {data.id_document ? data.id_document.name : 'Choose File'}
                                            </Button>
                                        </div>
                                        {previews.id_document && (
                                            <img src={previews.id_document} alt="ID preview" className="mt-2 h-32 rounded-lg border object-cover" />
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            JPG, PNG or PDF (max 5MB). Ensure all details are clearly visible.
                                        </p>
                                        <InputError message={errors.id_document} />
                                    </div>

                                    {/* Business Document Upload */}
                                    <div className="space-y-2">
                                        <Label htmlFor="business_document">
                                            Business Registration Document <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="flex items-center gap-4">
                                            <Input
                                                id="business_document"
                                                type="file"
                                                accept="image/jpeg,image/png,application/pdf"
                                                onChange={(e) => handleFileChange('business_document', e.target.files?.[0] || null)}
                                                className="hidden"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => document.getElementById('business_document')?.click()}
                                                className="w-full"
                                            >
                                                <Upload className="mr-2 h-4 w-4" />
                                                {data.business_document ? data.business_document.name : 'Choose File'}
                                            </Button>
                                        </div>
                                        {previews.business_document && (
                                            <img src={previews.business_document} alt="Business doc preview" className="mt-2 h-32 rounded-lg border object-cover" />
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            Certificate of incorporation, business license, or tax certificate.
                                        </p>
                                        <InputError message={errors.business_document} />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Step 3: Selfie */}
                        {currentStep === 3 && (
                            <Card>
                                <CardHeader className="pb-4">
                                    <CardTitle className="text-lg">{t('settings.selfie_verification')}</CardTitle>
                                    <CardDescription>
                                        Take a photo of yourself holding your ID next to your face
                                    </CardDescription>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    <Alert className="border-blue-200 bg-blue-50 dark:border-blue-900/50 dark:bg-blue-900/20">
                                        <Camera className="h-4 w-4 text-blue-600 dark:text-blue-400" />
                                        <AlertTitle className="text-blue-900 dark:text-blue-100">Photo Requirements</AlertTitle>
                                        <AlertDescription className="text-blue-800 dark:text-blue-200">
                                            <ul className="mt-2 space-y-1 text-sm">
                                                <li>• Your face must be clearly visible</li>
                                                <li>• Hold your ID document next to your face</li>
                                                <li>• Details on the ID must be readable</li>
                                                <li>• Good lighting and focus required</li>
                                                <li>• No filters or editing</li>
                                            </ul>
                                        </AlertDescription>
                                    </Alert>

                                    <div className="space-y-2">
                                        <Label htmlFor="selfie_with_id">
                                            Selfie with ID <span className="text-destructive">*</span>
                                        </Label>
                                        <div className="flex items-center gap-4">
                                            <Input
                                                id="selfie_with_id"
                                                type="file"
                                                accept="image/jpeg,image/png"
                                                onChange={(e) => handleFileChange('selfie_with_id', e.target.files?.[0] || null)}
                                                className="hidden"
                                            />
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => document.getElementById('selfie_with_id')?.click()}
                                                className="w-full"
                                            >
                                                <Camera className="mr-2 h-4 w-4" />
                                                {data.selfie_with_id ? data.selfie_with_id.name : 'Take or Upload Photo'}
                                            </Button>
                                        </div>
                                        {previews.selfie_with_id && (
                                            <img src={previews.selfie_with_id} alt="Selfie preview" className="mt-2 h-48 rounded-lg border object-cover" />
                                        )}
                                        <p className="text-xs text-muted-foreground">
                                            JPG or PNG (max 5MB)
                                        </p>
                                        <InputError message={errors.selfie_with_id} />
                                    </div>
                                </CardContent>
                            </Card>
                        )}

                        {/* Navigation Buttons */}
                        <Card>
                            <CardContent className="pt-6">
                                <div className="flex justify-between gap-4">
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => setCurrentStep(Math.max(1, currentStep - 1))}
                                        disabled={currentStep === 1}
                                    >
                                        <ChevronLeft className="mr-2 h-4 w-4" />
                                        Previous
                                    </Button>

                                    {currentStep < 3 ? (
                                        <Button
                                            type="button"
                                            onClick={() => setCurrentStep(currentStep + 1)}
                                            disabled={!canGoNext()}
                                        >
                                            Next
                                            <ChevronRight className="ml-2 h-4 w-4" />
                                        </Button>
                                    ) : (
                                        <Button
                                            type="submit"
                                            disabled={processing || !data.selfie_with_id}
                                        >
                                            {processing ? 'Submitting...' : 'Submit for Review'}
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </form>

                    {/* Privacy Notice */}
                    <Card>
                        <CardContent className="pt-6">
                            <div className="space-y-3 text-sm text-muted-foreground">
                                <h4 className="font-medium text-foreground">Privacy & Review Process</h4>
                                <p>
                                    All documents are encrypted and stored securely. Our compliance team will review your
                                    submission within 24-48 hours. You'll receive an email notification with the results.
                                </p>
                                <p>
                                    By submitting, you confirm that all information provided is accurate and the documents are genuine.
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
