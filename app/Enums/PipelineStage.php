<?php
namespace App\Enums;
enum PipelineStage: string {
    case APPLIED='applied'; case SCREENED='screened'; case CONFIRMED='confirmed'; case SCHEDULED='scheduled'; case COMPLETED='completed'; case NO_SHOW='no_show'; case PAID='paid'; case REJECTED='rejected';
    public function label(): string {
        return match($this){ self::APPLIED=>'Applied', self::SCREENED=>'Screened', self::CONFIRMED=>'Confirmed', self::SCHEDULED=>'Scheduled', self::COMPLETED=>'Completed', self::NO_SHOW=>'No Show', self::PAID=>'Paid', self::REJECTED=>'Rejected' };
    }
}
