import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger } from '@/components/ui/dialog';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Plus, Trash2, Edit, Save, AlertCircle, Sparkles, Phone, User } from 'lucide-react';
import { useState, FormEvent } from 'react';
import { useForm, router } from '@inertiajs/react';

interface CampaignContact {
    id: number;
    phone_number: string;
    first_name?: string;
    last_name?: string;
    email?: string;
    company?: string;
    variables?: Record<string, string>;
    status: string;
}

interface Props {
    campaignId: number;
    contacts: CampaignContact[];
    onUpdate?: () => void;
}

interface VariableField {
    key: string;
    value: string;
}

export default function CampaignContactManager({ campaignId, contacts, onUpdate }: Props) {
    const [isAddDialogOpen, setIsAddDialogOpen] = useState(false);
    const [editingContact, setEditingContact] = useState<CampaignContact | null>(null);
    const [customVariables, setCustomVariables] = useState<VariableField[]>([{ key: '', value: '' }]);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        phone_number: '',
        first_name: '',
        last_name: '',
        email: '',
        company: '',
        variables: {} as Record<string, string>,
    });

    // Get all unique variable keys from existing contacts
    const getAllVariableKeys = (): string[] => {
        const keys = new Set<string>();
        contacts.forEach(contact => {
            if (contact.variables) {
                Object.keys(contact.variables).forEach(key => keys.add(key));
            }
        });
        return Array.from(keys);
    };

    const existingVariableKeys = getAllVariableKeys();

    const handleAddVariable = () => {
        setCustomVariables([...customVariables, { key: '', value: '' }]);
    };

    const handleRemoveVariable = (index: number) => {
        setCustomVariables(customVariables.filter((_, i) => i !== index));
    };

    const handleVariableChange = (index: number, field: 'key' | 'value', value: string) => {
        const updated = [...customVariables];
        updated[index][field] = value;
        setCustomVariables(updated);
    };

    const prepareVariablesForSubmit = (): Record<string, string> => {
        const vars: Record<string, string> = {};
        customVariables.forEach(({ key, value }) => {
            if (key.trim() && value.trim()) {
                vars[key.trim()] = value.trim();
            }
        });
        return vars;
    };

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        
        const variables = prepareVariablesForSubmit();
        
        // Update form data with variables
        setData('variables', variables);
        
        if (editingContact) {
            // Update existing contact
            put(`/campaigns/${campaignId}/contacts/${editingContact.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    setEditingContact(null);
                    reset();
                    setCustomVariables([{ key: '', value: '' }]);
                    if (onUpdate) onUpdate();
                },
            });
        } else {
            // Create new contact
            post(`/campaigns/${campaignId}/contacts`, {
                preserveScroll: true,
                onSuccess: () => {
                    setIsAddDialogOpen(false);
                    reset();
                    setCustomVariables([{ key: '', value: '' }]);
                    if (onUpdate) onUpdate();
                },
            });
        }
    };

    const handleEdit = (contact: CampaignContact) => {
        setEditingContact(contact);
        setData({
            phone_number: contact.phone_number,
            first_name: contact.first_name || '',
            last_name: contact.last_name || '',
            email: contact.email || '',
            company: contact.company || '',
            variables: contact.variables || {},
        });

        // Load existing variables
        if (contact.variables && Object.keys(contact.variables).length > 0) {
            setCustomVariables(
                Object.entries(contact.variables).map(([key, value]) => ({ key, value }))
            );
        } else {
            setCustomVariables([{ key: '', value: '' }]);
        }
    };

    const handleDelete = (contact: CampaignContact) => {
        if (confirm(`Remove ${contact.first_name || contact.phone_number} from this campaign?`)) {
            router.delete(`/campaigns/${campaignId}/contacts/${contact.id}`, {
                preserveScroll: true,
                onSuccess: () => {
                    if (onUpdate) onUpdate();
                },
            });
        }
    };

    const handleCancelEdit = () => {
        setEditingContact(null);
        reset();
        setCustomVariables([{ key: '', value: '' }]);
    };

    return (
        <div className="space-y-4">
            {/* Header with Add Button */}
            <div className="flex items-center justify-between">
                <div>
                    <h3 className="text-lg font-semibold">Campaign Contacts</h3>
                    <p className="text-sm text-muted-foreground">
                        Manage contacts and their custom variables
                    </p>
                </div>
                <Dialog open={isAddDialogOpen} onOpenChange={setIsAddDialogOpen}>
                    <DialogTrigger asChild>
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Add Contact
                        </Button>
                    </DialogTrigger>
                    <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Add New Contact</DialogTitle>
                            <DialogDescription>
                                Add a contact manually with custom variables for personalization
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Standard Fields */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-semibold flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    Contact Information
                                </h4>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="phone_number">Phone Number *</Label>
                                        <Input
                                            id="phone_number"
                                            type="tel"
                                            placeholder="+1234567890"
                                            value={data.phone_number}
                                            onChange={(e) => setData('phone_number', e.target.value)}
                                            required
                                        />
                                        {errors.phone_number && (
                                            <p className="text-sm text-red-600">{errors.phone_number}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="email">Email</Label>
                                        <Input
                                            id="email"
                                            type="email"
                                            placeholder="john@example.com"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="first_name">First Name</Label>
                                        <Input
                                            id="first_name"
                                            placeholder="John"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="last_name">Last Name</Label>
                                        <Input
                                            id="last_name"
                                            placeholder="Doe"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2 col-span-2">
                                        <Label htmlFor="company">Company</Label>
                                        <Input
                                            id="company"
                                            placeholder="Acme Corp"
                                            value={data.company}
                                            onChange={(e) => setData('company', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Custom Variables */}
                            <div className="space-y-4 border-t pt-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-sm font-semibold flex items-center gap-2">
                                        <Sparkles className="h-4 w-4" />
                                        Custom Variables
                                    </h4>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleAddVariable}
                                    >
                                        <Plus className="h-3 w-3 mr-1" />
                                        Add Variable
                                    </Button>
                                </div>

                                {existingVariableKeys.length > 0 && (
                                    <Alert>
                                        <AlertCircle className="h-4 w-4" />
                                        <AlertDescription className="text-xs">
                                            <strong>Existing variables in campaign:</strong>{' '}
                                            {existingVariableKeys.map((key, idx) => (
                                                <Badge key={idx} variant="secondary" className="ml-1">
                                                    {key}
                                                </Badge>
                                            ))}
                                        </AlertDescription>
                                    </Alert>
                                )}

                                <div className="space-y-2">
                                    {customVariables.map((variable, index) => (
                                        <div key={index} className="flex gap-2">
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Variable name (e.g., store_name)"
                                                    value={variable.key}
                                                    onChange={(e) => handleVariableChange(index, 'key', e.target.value)}
                                                    className="font-mono text-sm"
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Value (e.g., Downtown Store)"
                                                    value={variable.value}
                                                    onChange={(e) => handleVariableChange(index, 'value', e.target.value)}
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveVariable(index)}
                                                disabled={customVariables.length === 1}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Custom variables can be used in your campaign message using {'{{'} variableName {'}}'} syntax
                                </p>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        setIsAddDialogOpen(false);
                                        reset();
                                        setCustomVariables([{ key: '', value: '' }]);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Adding...' : 'Add Contact'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            </div>

            {/* Contacts Table */}
            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Contact List</CardTitle>
                    <CardDescription>{contacts.length} contacts in campaign</CardDescription>
                </CardHeader>
                <CardContent>
                    {contacts.length === 0 ? (
                        <div className="text-center py-12">
                            <Phone className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground mb-4">
                                No contacts yet. Add contacts manually or upload a CSV file.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Phone</TableHead>
                                        <TableHead>Name</TableHead>
                                        <TableHead>Company</TableHead>
                                        <TableHead>Custom Variables</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {contacts.map((contact) => (
                                        <TableRow key={contact.id}>
                                            <TableCell className="font-mono text-sm">
                                                {contact.phone_number}
                                            </TableCell>
                                            <TableCell>
                                                {contact.first_name || contact.last_name
                                                    ? `${contact.first_name || ''} ${contact.last_name || ''}`.trim()
                                                    : '-'}
                                            </TableCell>
                                            <TableCell>{contact.company || '-'}</TableCell>
                                            <TableCell>
                                                {contact.variables && Object.keys(contact.variables).length > 0 ? (
                                                    <div className="flex gap-1 flex-wrap">
                                                        {Object.entries(contact.variables).slice(0, 3).map(([key, value], idx) => (
                                                            <Badge key={idx} variant="secondary" className="text-xs">
                                                                {key}: {String(value).substring(0, 20)}
                                                                {String(value).length > 20 ? '...' : ''}
                                                            </Badge>
                                                        ))}
                                                        {Object.keys(contact.variables).length > 3 && (
                                                            <Badge variant="outline" className="text-xs">
                                                                +{Object.keys(contact.variables).length - 3} more
                                                            </Badge>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-muted-foreground text-sm">None</span>
                                                )}
                                            </TableCell>
                                            <TableCell>
                                                <Badge variant={
                                                    contact.status === 'completed' ? 'default' :
                                                    contact.status === 'failed' ? 'destructive' :
                                                    'secondary'
                                                }>
                                                    {contact.status}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleEdit(contact)}
                                                    >
                                                        <Edit className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => handleDelete(contact)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-red-600" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </div>
                    )}
                </CardContent>
            </Card>

            {/* Edit Dialog */}
            {editingContact && (
                <Dialog open={true} onOpenChange={(open) => !open && handleCancelEdit()}>
                    <DialogContent className="max-w-3xl max-h-[90vh] overflow-y-auto">
                        <DialogHeader>
                            <DialogTitle>Edit Contact</DialogTitle>
                            <DialogDescription>
                                Update contact information and custom variables
                            </DialogDescription>
                        </DialogHeader>
                        
                        <form onSubmit={handleSubmit} className="space-y-6">
                            {/* Same form fields as Add Contact */}
                            <div className="space-y-4">
                                <h4 className="text-sm font-semibold flex items-center gap-2">
                                    <User className="h-4 w-4" />
                                    Contact Information
                                </h4>
                                
                                <div className="grid grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="edit_phone_number">Phone Number *</Label>
                                        <Input
                                            id="edit_phone_number"
                                            type="tel"
                                            value={data.phone_number}
                                            onChange={(e) => setData('phone_number', e.target.value)}
                                            required
                                        />
                                        {errors.phone_number && (
                                            <p className="text-sm text-red-600">{errors.phone_number}</p>
                                        )}
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit_email">Email</Label>
                                        <Input
                                            id="edit_email"
                                            type="email"
                                            value={data.email}
                                            onChange={(e) => setData('email', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit_first_name">First Name</Label>
                                        <Input
                                            id="edit_first_name"
                                            value={data.first_name}
                                            onChange={(e) => setData('first_name', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="edit_last_name">Last Name</Label>
                                        <Input
                                            id="edit_last_name"
                                            value={data.last_name}
                                            onChange={(e) => setData('last_name', e.target.value)}
                                        />
                                    </div>

                                    <div className="space-y-2 col-span-2">
                                        <Label htmlFor="edit_company">Company</Label>
                                        <Input
                                            id="edit_company"
                                            value={data.company}
                                            onChange={(e) => setData('company', e.target.value)}
                                        />
                                    </div>
                                </div>
                            </div>

                            {/* Custom Variables */}
                            <div className="space-y-4 border-t pt-4">
                                <div className="flex items-center justify-between">
                                    <h4 className="text-sm font-semibold flex items-center gap-2">
                                        <Sparkles className="h-4 w-4" />
                                        Custom Variables
                                    </h4>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={handleAddVariable}
                                    >
                                        <Plus className="h-3 w-3 mr-1" />
                                        Add Variable
                                    </Button>
                                </div>

                                <div className="space-y-2">
                                    {customVariables.map((variable, index) => (
                                        <div key={index} className="flex gap-2">
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Variable name"
                                                    value={variable.key}
                                                    onChange={(e) => handleVariableChange(index, 'key', e.target.value)}
                                                    className="font-mono text-sm"
                                                />
                                            </div>
                                            <div className="flex-1">
                                                <Input
                                                    placeholder="Value"
                                                    value={variable.value}
                                                    onChange={(e) => handleVariableChange(index, 'value', e.target.value)}
                                                />
                                            </div>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => handleRemoveVariable(index)}
                                            >
                                                <Trash2 className="h-4 w-4 text-red-600" />
                                            </Button>
                                        </div>
                                    ))}
                                </div>
                            </div>

                            <DialogFooter>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={handleCancelEdit}
                                >
                                    Cancel
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    <Save className="mr-2 h-4 w-4" />
                                    {processing ? 'Saving...' : 'Save Changes'}
                                </Button>
                            </DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>
            )}
        </div>
    );
}
