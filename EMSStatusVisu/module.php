<?php

declare(strict_types=1);

class SulzbannEMSStatusVisu extends IPSModule
{
    private const EMS_STATUS_ID = 35790;
    private const STRATEGY_ID = 55441;
    private const NEXT_SOC_TARGET_ID = 13390;
    private const SOC_CHARGE_NEED_ID = 58787;
    private const BATTERY_LIMIT_ID = 47350;
    private const CURTAILMENT_ACTIVE_ID = 14640;
    private const GRID_EXPORT_ID = 47045;
    private const GRID_IMPORT_ID = 15256;
    private const BATTERY_SOC_ID = 46752;
    private const PV_POWER_ID = 24848;
    private const FORECAST_ENERGY_ID = 23296;
    private const FORECAST_PEAK_ID = 48705;
    private const FORECAST_DURATION_ID = 43728;

    public function Create(): void
    {
        parent::Create();

        // Bewährter Typ der funktionierenden WP-Regelung-Visu.
        $this->SetVisualizationType(1);
    }

    public function ApplyChanges(): void
    {
        parent::ApplyChanges();

        $this->SetVisualizationType(1);

        foreach ($this->GetSourceVariableIDs() as $variableID) {
            if ($variableID > 0 && IPS_VariableExists($variableID)) {
                $this->RegisterMessage($variableID, VM_UPDATE);
            }
        }
    }

    public function GetVisualizationTile(): string
    {
        $file = __DIR__ . '/module.html';

        if (!is_file($file)) {
            return '<div style="padding:60px 12px;color:var(--content-color)">module.html fehlt.</div>';
        }

        $html = file_get_contents($file);

        if ($html === false) {
            return '<div style="padding:60px 12px;color:var(--content-color)">module.html konnte nicht geladen werden.</div>';
        }

        $initialJson = json_encode(
            $this->BuildVisualizationData(),
            JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_HEX_TAG
            | JSON_HEX_AMP
            | JSON_HEX_APOS
            | JSON_HEX_QUOT
        );

        if ($initialJson === false) {
            $initialJson = '{}';
        }

        return str_replace('__SBEMSV_INITIAL_DATA__', $initialJson, $html);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data): void
    {
        parent::MessageSink($TimeStamp, $SenderID, $Message, $Data);

        if ($Message !== VM_UPDATE) {
            return;
        }

        $this->SendLiveValues();
    }

    public function RequestAction($Ident, $Value): void
    {
        if ($Ident !== 'Refresh') {
            throw new Exception('Invalid Ident: ' . $Ident);
        }

        $this->SendLiveValues();
    }

    public function Update(): void
    {
        $this->SendLiveValues();
    }

    private function SendLiveValues(): void
    {
        $json = json_encode(
            $this->BuildVisualizationData(),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json !== false) {
            $this->UpdateVisualizationValue($json);
        }
    }

    private function BuildVisualizationData(): array
    {
        $export = $this->ReadFloatNullable(self::GRID_EXPORT_ID);
        $import = $this->ReadFloatNullable(self::GRID_IMPORT_ID);

        $grid = null;
        $gridMode = 'neutral';

        if ($import !== null && $import > 0.01) {
            $grid = $import;
            $gridMode = 'import';
        } elseif ($export !== null && $export > 0.01) {
            $grid = -$export;
            $gridMode = 'export';
        } elseif ($import !== null || $export !== null) {
            $grid = 0.0;
        }

        $pvWatts = $this->ReadFloatNullable(self::PV_POWER_ID);

        return [
            'timestamp' => time(),
            'strategy' => $this->ReadStringNullable(self::STRATEGY_ID),
            'status' => $this->ReadStringNullable(self::EMS_STATUS_ID),
            'target' => $this->ReadStringNullable(self::NEXT_SOC_TARGET_ID),
            'socNeed' => $this->ReadFloatNullable(self::SOC_CHARGE_NEED_ID),
            'forecastEnergy' => $this->ReadFloatNullable(self::FORECAST_ENERGY_ID),
            'forecastPeak' => $this->ReadFloatNullable(self::FORECAST_PEAK_ID),
            'forecastDuration' => $this->ReadFloatNullable(self::FORECAST_DURATION_ID),
            'pv' => $pvWatts === null ? null : $pvWatts / 1000.0,
            'grid' => $grid,
            'gridMode' => $gridMode,
            'soc' => $this->ReadFloatNullable(self::BATTERY_SOC_ID),
            'limit' => $this->ReadFloatNullable(self::BATTERY_LIMIT_ID),
            'curtailmentActive' => $this->ReadBoolNullable(self::CURTAILMENT_ACTIVE_ID)
        ];
    }

    private function GetSourceVariableIDs(): array
    {
        return [
            self::EMS_STATUS_ID,
            self::STRATEGY_ID,
            self::NEXT_SOC_TARGET_ID,
            self::SOC_CHARGE_NEED_ID,
            self::BATTERY_LIMIT_ID,
            self::CURTAILMENT_ACTIVE_ID,
            self::GRID_EXPORT_ID,
            self::GRID_IMPORT_ID,
            self::BATTERY_SOC_ID,
            self::PV_POWER_ID,
            self::FORECAST_ENERGY_ID,
            self::FORECAST_PEAK_ID,
            self::FORECAST_DURATION_ID
        ];
    }

    private function ReadFloatNullable(int $variableID): ?float
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }

        $value = GetValue($variableID);

        return is_numeric($value) ? (float) $value : null;
    }

    private function ReadStringNullable(int $variableID): ?string
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }

        $value = trim((string) GetValue($variableID));

        return $value === '' ? null : $value;
    }

    private function ReadBoolNullable(int $variableID): ?bool
    {
        if (!IPS_VariableExists($variableID)) {
            return null;
        }

        return (bool) GetValue($variableID);
    }
}
