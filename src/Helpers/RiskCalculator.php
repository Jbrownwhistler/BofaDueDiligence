<?php
class RiskCalculator {
    /**
     * Calculate risk score: (amount / 10000) × country_coefficient × asset_coefficient
     */
    public static function calculate(float $amount, string $country, string $assetType): float {
        $countryCoeff = self::getCountryCoefficient($country);
        $assetCoeff = self::getAssetCoefficient($assetType);
        $amountFactor = $amount / 10000;
        $score = round($amountFactor * $countryCoeff * $assetCoeff, 2);
        return min($score, 99.99); // Cap at 99.99
    }

    public static function getCountryCoefficient(string $country): float {
        $db = getDB();
        $stmt = $db->prepare('SELECT coefficient_risque FROM risk_countries WHERE nom_pays = ?');
        $stmt->execute([$country]);
        $result = $stmt->fetch();
        return $result ? (float)$result['coefficient_risque'] : 1.50; // Default to moderate risk
    }

    public static function getAssetCoefficient(string $assetType): float {
        $db = getDB();
        $stmt = $db->prepare('SELECT coefficient_risque FROM risk_asset_types WHERE nom_type = ?');
        $stmt->execute([$assetType]);
        $result = $stmt->fetch();
        return $result ? (float)$result['coefficient_risque'] : 1.00;
    }

    public static function getThreshold(): float {
        $db = getDB();
        $stmt = $db->query("SELECT valeur FROM settings WHERE cle = 'seuil_double_validation'");
        $result = $stmt->fetch();
        return $result ? (float)$result['valeur'] : 7.5;
    }

    public static function requiresSupervisor(float $score): bool {
        return $score >= self::getThreshold();
    }

    public static function getScoreClass(float $score): string {
        if ($score < 3) return 'success';
        if ($score < 7) return 'warning';
        return 'danger';
    }

    public static function getScoreLabel(float $score): string {
        if ($score < 3) return 'Faible';
        if ($score < 7) return 'Modéré';
        return 'Élevé';
    }
}
