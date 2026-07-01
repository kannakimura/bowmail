<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// 一括アップロード用のExcelテンプレートをpublic/downloadsに生成するコマンド
class GenerateBulkTemplate extends Command
{
    protected $signature   = 'mailflow:generate-template';
    protected $description = '一括アップロード用Excelテンプレートを生成する';

    public function handle(): void
    {
        $headers     = array_values(config('bulk_import.columns', []));
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();

        // 1行目にヘッダーを書き込む
        foreach ($headers as $col => $label) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $label);
        }

        $dir = public_path('downloads');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($dir . '/template.xlsx');
        $spreadsheet->disconnectWorksheets();

        $this->info('テンプレートを生成しました: public/downloads/template.xlsx');
    }
}
