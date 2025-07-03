<?php

namespace App\Filament\Resources\SiteResource\Actions;

use Filament\Forms;
use App\Models\Models\Site;
use Filament\Actions\Action;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\Storage;
use Filament\Notifications\Notification;

class ImportSitesXLS extends Action
{
    public static function make(string $name = null): static
    {
        return parent::make($name)
            ->label('Upload XLS')
            ->icon('heroicon-o-arrow-up-tray')
            ->form([
                Forms\Components\FileUpload::make('xls_file')
                    ->label('XLS File')
                    ->required()
                    ->acceptedFileTypes([
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'text/csv',
                        '.xls', '.xlsx', '.csv',
                    ])
                    ->disk('local')
                    ->directory('temp-imports'),
            ])
            ->action(function (array $data, $livewire) {
                $fileState = $data['xls_file'];
                
                try {
                    // Handle file path resolution
                    if ($fileState instanceof \Illuminate\Http\UploadedFile) {
                        $filePath = $fileState->getRealPath();
                    } else {
                        $tempRelativePath = $fileState;
                        
                        if (!Storage::disk('local')->exists($tempRelativePath)) {
                            throw new \RuntimeException('Uploaded file was not found. Please re-upload.');
                        }
                        
                        $filePath = Storage::disk('local')->path($tempRelativePath);
                        
                        if (!file_exists($filePath)) {
                            throw new \RuntimeException('File not accessible. Please re-upload.');
                        }
                    }

                    // Read the Excel file properly
                    $collection = Excel::toCollection(null, $filePath);
                    
                    if ($collection->isEmpty() || $collection->first()->isEmpty()) {
                        throw new \RuntimeException('The Excel file appears to be empty or corrupted.');
                    }
                    
                    $rows = $collection->first(); // Get first sheet
                    $imported = 0;
                    $errors = [];
                    
                    DB::beginTransaction();
                    
                    // Skip the header row (first row)
                    foreach ($rows->skip(1) as $index => $row) {
                        $rowNumber = $index + 2; // +2 because we skipped header and arrays are 0-indexed
                        
                        // Get values from the row (0-indexed) - row is now a collection
                        $siteName = isset($row[0]) ? trim($row[0]) : null;
                        $adminFee = isset($row[1]) ? $row[1] : null;
                        
                        // Skip empty rows
                        if (empty($siteName) && empty($adminFee)) {
                            continue;
                        }
                        
                        // Validate data
                        if (empty($siteName)) {
                            $errors[] = "Row $rowNumber: Site name is required";
                            continue;
                        }
                        
                        if ($adminFee === null || $adminFee === '') {
                            $errors[] = "Row $rowNumber: Admin fee is required";
                            continue;
                        }
                        
                        if (!is_numeric($adminFee)) {
                            $errors[] = "Row $rowNumber: Admin fee must be a number";
                            continue;
                        }
                        
                        // Generate URL from site name if it looks like a domain
                        $url = $siteName;
                        if (!filter_var($siteName, FILTER_VALIDATE_URL)) {
                            // If it's not a full URL, assume it's a domain and add https://
                            $url = 'https://' . $siteName;
                        }
                        
                        try {
                            Site::updateOrCreate([
                                'name' => $siteName,
                            ], [
                                'url' => $url,
                                'admin_fee' => (float) $adminFee,
                                'is_active' => true,
                            ]);
                            $imported++;
                        } catch (\Exception $e) {
                            $errors[] = "Row $rowNumber: Failed to import - " . $e->getMessage();
                        }
                    }
                    
                    DB::commit();
                    
                    // Clean up temporary file if it was created
                    if (!($fileState instanceof \Illuminate\Http\UploadedFile)) {
                        Storage::disk('local')->delete($tempRelativePath);
                    }
                    
                    // Show results
                    if ($imported > 0) {
                        $message = "$imported sites imported successfully.";
                        if (!empty($errors)) {
                            $message .= "\n\nErrors encountered:\n" . implode("\n", array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $message .= "\n... and " . (count($errors) - 10) . " more errors.";
                            }
                        }
                        
                        Notification::make()
                            ->title('Import Complete')
                            ->body($message)
                            ->success()
                            ->send();
                    } else {
                        $message = "No sites were imported.";
                        if (!empty($errors)) {
                            $message .= "\n\nErrors encountered:\n" . implode("\n", array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $message .= "\n... and " . (count($errors) - 10) . " more errors.";
                            }
                        }
                        
                        Notification::make()
                            ->title('Import Failed')
                            ->body($message)
                            ->danger()
                            ->send();
                    }
                        
                } catch (\Throwable $e) {
                    DB::rollBack();
                    
                    // Clean up temporary file on error
                    if (!($fileState instanceof \Illuminate\Http\UploadedFile) && isset($tempRelativePath)) {
                        Storage::disk('local')->delete($tempRelativePath);
                    }
                    
                    Notification::make()
                        ->title('Import Failed')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}