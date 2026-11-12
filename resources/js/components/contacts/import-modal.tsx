import { useState, useEffect, useRef } from 'react';
import { router } from '@inertiajs/react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Checkbox } from '@/components/ui/checkbox';
import { Progress } from '@/components/ui/progress';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Upload, Download, AlertCircle, CheckCircle2, Loader2 } from 'lucide-react';
import { Card } from '@/components/ui/card';
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table';

interface ContactTag {
  id: number;
  name: string;
}

interface ContactList {
  id: number;
  name: string;
}

interface ImportModalProps {
  open: boolean;
  onClose: () => void;
  tags?: ContactTag[];
  lists?: ContactList[];
}

interface CSVPreview {
  headers: string[];
  rows: string[][];
  total_rows: number;
}

interface ColumnMapping {
  [key: string]: string;
}

interface ImportStatus {
  id: number;
  status: 'pending' | 'processing' | 'completed' | 'failed';
  total_rows: number;
  processed_rows: number;
  successful_rows: number;
  failed_rows: number;
  progress_percentage: number;
  error_details?: Record<string, unknown>;
  completed_at?: string;
}

const FIELD_OPTIONS = [
  { value: 'skip', label: 'Skip this column' },
  { value: 'first_name', label: 'First Name' },
  { value: 'last_name', label: 'Last Name' },
  { value: 'phone_number', label: 'Phone Number *' },
  { value: 'email', label: 'Email' },
  { value: 'company', label: 'Company' },
  { value: 'notes', label: 'Notes' },
];

export function ImportModal({ open, onClose, tags = [], lists = [] }: ImportModalProps) {
  const [step, setStep] = useState<'upload' | 'mapping' | 'importing' | 'completed'>('upload');
  const [file, setFile] = useState<File | null>(null);
  const [csvPreview, setCsvPreview] = useState<CSVPreview | null>(null);
  const [columnMapping, setColumnMapping] = useState<ColumnMapping>({});
  const [selectedTags, setSelectedTags] = useState<number[]>([]);
  const [selectedLists, setSelectedLists] = useState<number[]>([]);
  const [skipDuplicates, setSkipDuplicates] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [uploadProgress, setUploadProgress] = useState(0);
  const [importId, setImportId] = useState<number | null>(null);
  const [importStatus, setImportStatus] = useState<ImportStatus | null>(null);
  const pollInterval = useRef<NodeJS.Timeout | null>(null);
  const [error, setError] = useState<string | null>(null);

  const handleFileChange = async (e: React.ChangeEvent<HTMLInputElement>) => {
    const selectedFile = e.target.files?.[0];
    if (!selectedFile) return;

    // Validate file type
    if (!selectedFile.name.endsWith('.csv')) {
      setError('Please select a CSV file');
      return;
    }

    // Validate file size (50MB)
    if (selectedFile.size > 50 * 1024 * 1024) {
      setError('File size must be less than 50MB');
      return;
    }

    setFile(selectedFile);
    setError(null);
    setUploading(true);

    // Upload file for preview
    const formData = new FormData();
    formData.append('file', selectedFile);

    try {
      const response = await fetch('/contacts/import/preview', {
        method: 'POST',
        body: formData,
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
      });

      if (!response.ok) {
        const errorData = await response.json().catch(() => ({ error: 'Failed to process CSV file' }));
        setError(errorData.error || 'Failed to process CSV file');
        setFile(null);
        setUploading(false);
        return;
      }

      const result = await response.json();

      if (result.error) {
        setError(result.error);
        setFile(null);
      } else {
        setCsvPreview(result);
        // Auto-map columns based on header names
        const autoMapping = autoMapColumns(result.headers);
        setColumnMapping(autoMapping);
        setStep('mapping');
      }
    } catch (error) {
      console.error('Import preview error:', error);
      setError('Failed to preview CSV file. Please try again.');
      setFile(null);
    } finally {
      setUploading(false);
    }
  };

  const autoMapColumns = (headers: string[]): ColumnMapping => {
    const mapping: ColumnMapping = {};
    
    headers.forEach((header, index) => {
      const normalized = header.toLowerCase().trim();
      
      // Auto-detect common column names
      if (normalized.includes('first') && normalized.includes('name')) {
        mapping[index] = 'first_name';
      } else if (normalized.includes('last') && normalized.includes('name')) {
        mapping[index] = 'last_name';
      } else if (normalized.includes('phone') || normalized.includes('mobile') || normalized.includes('cell')) {
        mapping[index] = 'phone_number';
      } else if (normalized.includes('email') || normalized.includes('mail')) {
        mapping[index] = 'email';
      } else if (normalized.includes('company') || normalized.includes('organization')) {
        mapping[index] = 'company';
      } else if (normalized.includes('note') || normalized.includes('comment')) {
        mapping[index] = 'notes';
      } else {
        mapping[index] = 'skip';
      }
    });

    return mapping;
  };

  const handleColumnMappingChange = (columnIndex: number, field: string) => {
    setColumnMapping(prev => ({
      ...prev,
      [columnIndex]: field,
    }));
  };

  const handleImport = async () => {
    if (!file) return;

    // Validate that phone_number is mapped
    const hasPhoneMapping = Object.values(columnMapping).includes('phone_number');
    if (!hasPhoneMapping) {
      setError('You must map at least one column to "Phone Number"');
      return;
    }

    setError(null);
    setStep('importing');
    setUploadProgress(0);

    const formData = new FormData();
    formData.append('file', file);
    formData.append('column_mapping', JSON.stringify(columnMapping));
    
    if (selectedTags.length > 0) {
      selectedTags.forEach(tagId => formData.append('tag_ids[]', tagId.toString()));
    }
    
    if (selectedLists.length > 0) {
      selectedLists.forEach(listId => formData.append('list_ids[]', listId.toString()));
    }

    try {
      const response = await fetch('/contacts-import', {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
        },
        body: formData,
      });

      const data = await response.json();

      if (response.ok && data.success) {
        // Start polling for import status
        setImportId(data.import_id);
        startPolling(data.import_id);
      } else {
        setError(data.error || 'Failed to start import. Please try again.');
        setStep('mapping');
      }
    } catch (err) {
      console.error('Import error:', err);
      setError('Failed to start import. Please try again.');
      setStep('mapping');
    }
  };

  const startPolling = (id: number) => {
    // Clear any existing interval
    if (pollInterval.current) {
      clearInterval(pollInterval.current);
    }

    // Poll every 2 seconds
    pollInterval.current = setInterval(async () => {
      try {
        const response = await fetch(`/contacts/import/${id}/status`);
        const status: ImportStatus = await response.json();
        
        setImportStatus(status);
        setUploadProgress(status.progress_percentage);

        // Stop polling if completed or failed
        if (status.status === 'completed' || status.status === 'failed') {
          if (pollInterval.current) {
            clearInterval(pollInterval.current);
            pollInterval.current = null;
          }

          if (status.status === 'completed') {
            setStep('completed');
            // Reload contacts list
            router.reload({ only: ['contacts'] });
          } else {
            const errorMessage = typeof status.error_details?.message === 'string' 
              ? status.error_details.message 
              : 'Import failed';
            setError(errorMessage);
            setStep('mapping');
          }
        }
      } catch (err) {
        console.error('Status poll error:', err);
      }
    }, 2000);
  };

  // Cleanup polling on unmount
  useEffect(() => {
    return () => {
      if (pollInterval.current) {
        clearInterval(pollInterval.current);
      }
    };
  }, []);

  const handleClose = () => {
    // Stop polling
    if (pollInterval.current) {
      clearInterval(pollInterval.current);
      pollInterval.current = null;
    }

    // Reset state
    setStep('upload');
    setFile(null);
    setCsvPreview(null);
    setColumnMapping({});
    setSelectedTags([]);
    setSelectedLists([]);
    setSkipDuplicates(true);
    setUploadProgress(0);
    setError(null);
    setImportId(null);
    setImportStatus(null);
    onClose();
  };

  const downloadTemplate = () => {
    window.location.href = '/contacts-export?template=1';
  };

  const toggleTag = (tagId: number) => {
    setSelectedTags(prev => 
      prev.includes(tagId) 
        ? prev.filter(id => id !== tagId)
        : [...prev, tagId]
    );
  };

  const toggleList = (listId: number) => {
    setSelectedLists(prev => 
      prev.includes(listId) 
        ? prev.filter(id => id !== listId)
        : [...prev, listId]
    );
  };

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-5xl max-h-[95vh] overflow-y-auto">
        <DialogHeader>
          <DialogTitle>Import Contacts</DialogTitle>
          <DialogDescription>
            Upload a CSV file to import your contacts. We support files up to 50MB with automatic column mapping.
          </DialogDescription>
        </DialogHeader>

        {error && (
          <Alert variant="destructive">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>{error}</AlertDescription>
          </Alert>
        )}

        {/* Step 1: Upload */}
        {step === 'upload' && (
          <div className="space-y-6">
            <div className="bg-blue-50 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
              <h3 className="font-semibold mb-2">CSV Format Requirements</h3>
              <ul className="text-sm space-y-1 text-muted-foreground">
                <li>✓ Required: Phone Number column</li>
                <li>✓ Optional: First Name, Last Name, Email, Company, Notes</li>
                <li>✓ Phone numbers should include country code (e.g., +1234567890)</li>
                <li>✓ Maximum file size: 50MB</li>
                <li>✓ Supports up to 100,000 contacts per import</li>
              </ul>
              <Button
                variant="outline"
                size="sm"
                className="mt-4"
                onClick={downloadTemplate}
              >
                <Download className="mr-2 h-4 w-4" />
                Download Template
              </Button>
            </div>

            <div className="space-y-2">
              <Label htmlFor="file">Select CSV File *</Label>
              <div className="border-2 border-dashed rounded-lg p-8 text-center hover:bg-muted/50 transition-colors">
                <Upload className="mx-auto h-12 w-12 text-muted-foreground mb-4" />
                <Input
                  id="file"
                  type="file"
                  accept=".csv"
                  onChange={handleFileChange}
                  className="max-w-xs mx-auto cursor-pointer"
                  disabled={uploading}
                />
                {file && (
                  <p className="mt-2 text-sm text-muted-foreground">
                    Selected: {file.name} ({(file.size / 1024 / 1024).toFixed(2)} MB)
                  </p>
                )}
              </div>
              {uploading && (
                <div className="mt-4">
                  <p className="text-sm text-muted-foreground mb-2">Analyzing CSV file...</p>
                  <Progress value={50} />
                </div>
              )}
            </div>
          </div>
        )}

        {/* Step 2: Column Mapping */}
        {step === 'mapping' && csvPreview && (
          <div className="space-y-6">
            <div>
              <h3 className="font-semibold mb-2">Map CSV Columns to Contact Fields</h3>
              <p className="text-sm text-muted-foreground mb-4">
                Found {csvPreview.total_rows} rows in your CSV file. Map each column to the appropriate contact field.
              </p>

              <Card className="p-4 mb-4">
                <div className="overflow-x-auto">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead className="w-[200px]">CSV Column</TableHead>
                        <TableHead className="w-[250px]">Maps To</TableHead>
                        <TableHead>Sample Data</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      {csvPreview.headers.map((header, index) => (
                        <TableRow key={index}>
                          <TableCell className="font-medium">{header}</TableCell>
                          <TableCell>
                            <Select
                              value={columnMapping[index] || ''}
                              onValueChange={(value) => handleColumnMappingChange(index, value)}
                            >
                              <SelectTrigger>
                                <SelectValue placeholder="Select field..." />
                              </SelectTrigger>
                              <SelectContent>
                                {FIELD_OPTIONS.map(option => (
                                  <SelectItem key={option.value} value={option.value}>
                                    {option.label}
                                  </SelectItem>
                                ))}
                              </SelectContent>
                            </Select>
                          </TableCell>
                          <TableCell className="text-muted-foreground text-sm">
                            {csvPreview.rows[0]?.[index] || '-'}
                          </TableCell>
                        </TableRow>
                      ))}
                    </TableBody>
                  </Table>
                </div>
              </Card>

              <div className="grid gap-4 md:grid-cols-2">
                <div className="space-y-2">
                  <Label>Additional Options</Label>
                  <div className="flex items-center space-x-2">
                    <Checkbox
                      id="skip_duplicates"
                      checked={skipDuplicates}
                      onCheckedChange={(checked) => setSkipDuplicates(checked as boolean)}
                    />
                    <label
                      htmlFor="skip_duplicates"
                      className="text-sm font-medium leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                    >
                      Skip duplicate phone numbers
                    </label>
                  </div>
                </div>

                {tags.length > 0 && (
                  <div className="space-y-2">
                    <Label>Add Tags (Optional)</Label>
                    <div className="space-y-2 max-h-32 overflow-y-auto border rounded-md p-2">
                      {tags.map(tag => (
                        <div key={tag.id} className="flex items-center space-x-2">
                          <Checkbox
                            id={`tag-${tag.id}`}
                            checked={selectedTags.includes(tag.id)}
                            onCheckedChange={() => toggleTag(tag.id)}
                          />
                          <label
                            htmlFor={`tag-${tag.id}`}
                            className="text-sm leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                          >
                            {tag.name}
                          </label>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                {lists.length > 0 && (
                  <div className="space-y-2">
                    <Label>Add to Lists (Optional)</Label>
                    <div className="space-y-2 max-h-32 overflow-y-auto border rounded-md p-2">
                      {lists.map(list => (
                        <div key={list.id} className="flex items-center space-x-2">
                          <Checkbox
                            id={`list-${list.id}`}
                            checked={selectedLists.includes(list.id)}
                            onCheckedChange={() => toggleList(list.id)}
                          />
                          <label
                            htmlFor={`list-${list.id}`}
                            className="text-sm leading-none peer-disabled:cursor-not-allowed peer-disabled:opacity-70"
                          >
                            {list.name}
                          </label>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            </div>

            <div className="flex justify-end gap-2">
              <Button variant="outline" onClick={handleClose}>
                Cancel
              </Button>
              <Button onClick={handleImport}>
                Import {csvPreview.total_rows} Contacts
              </Button>
            </div>
          </div>
        )}

        {/* Step 3: Importing */}
        {step === 'importing' && (
          <div className="space-y-6 py-8">
            <div className="text-center">
              <div className="mx-auto w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-4">
                <Loader2 className="h-8 w-8 text-blue-600 animate-spin" />
              </div>
              <h3 className="font-semibold text-lg mb-2">Your CSV file is importing!</h3>
              <p className="text-sm text-muted-foreground mb-6 max-w-md mx-auto">
                We will notify you when the process is done. You can close this modal and continue working.
              </p>
              {importStatus && importStatus.total_rows > 0 && (
                <div className="max-w-md mx-auto">
                  <Progress value={importStatus.progress_percentage} className="mb-3" />
                  <div className="text-sm space-y-1">
                    <p className="text-muted-foreground font-medium">
                      {importStatus.processed_rows} / {importStatus.total_rows} contacts processed
                    </p>
                    {importStatus.successful_rows > 0 && (
                      <p className="text-green-600">
                        ✓ Imported: {importStatus.successful_rows}
                      </p>
                    )}
                    {importStatus.failed_rows > 0 && (
                      <p className="text-red-600">
                        ✗ Failed: {importStatus.failed_rows}
                      </p>
                    )}
                  </div>
                </div>
              )}
              <Button onClick={handleClose} variant="outline" className="mt-6">
                Close & Continue Working
              </Button>
            </div>
          </div>
        )}

        {/* Step 4: Completed */}
        {step === 'completed' && (
          <div className="space-y-6 py-8">
            <div className="text-center">
              <div className="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                <CheckCircle2 className="h-8 w-8 text-green-600" />
              </div>
              <h3 className="font-semibold text-lg mb-2 text-green-600">Import Completed!</h3>
              {importStatus && (
                <div className="space-y-2 text-sm">
                  <p className="text-muted-foreground">
                    Total rows processed: {importStatus.processed_rows}
                  </p>
                  <p className="text-green-600 font-medium">
                    ✓ Successfully imported: {importStatus.successful_rows}
                  </p>
                  {importStatus.failed_rows > 0 && (
                    <p className="text-red-600">
                      ✗ Failed: {importStatus.failed_rows}
                    </p>
                  )}
                </div>
              )}
              <Button onClick={handleClose} className="mt-6">
                Close
              </Button>
            </div>
          </div>
        )}
      </DialogContent>
    </Dialog>
  );
}
