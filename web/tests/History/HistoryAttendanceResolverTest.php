<?php

declare(strict_types=1);

namespace App\Tests\History;

use App\History\HistoryAttendanceResolver;
use App\History\HistoryDocument;
use App\History\HistoryParser;
use PHPUnit\Framework\TestCase;

class HistoryAttendanceResolverTest extends TestCase
{
    public function testAnAnomalySiteHasItsSeriesAndYear(): void
    {
        $document = $this->document(<<<'MD'
            ## 2026

            ### ![Orion](../../app/app/src/main/assets/custom_medals/anomaly_orion_platinum.webp) Orion

            | Date | Site | Role |
            |---|---|---|
            | 16 May | 🇨🇿 Prague, Czechia | Primary |
            | 2 Nov | 🇪🇸 Valencia | cancelled because of [flooding](https://ingress.com) |

            ### Darsana Prime (likely)

            | Date | Site | Role |
            |---|---|---|
            | 23 Feb | Lisbon, Portugal | – |
            MD, 'anomalies');

        $orion = $document->blocks[2]->attendance;
        self::assertNotNull($orion[0]);
        self::assertSame('anomalies:2026-05-16:Prague, Czechia', $orion[0]->key);
        self::assertSame('2026-05-16', $orion[0]->date);
        self::assertSame('Orion Anomaly: Prague, Czechia', $orion[0]->text);
        self::assertNull($orion[1]);

        self::assertSame('Darsana Prime Anomaly: Lisbon, Portugal', $document->blocks[4]->attendance[0]?->text);
    }

    public function testAMissionDayWithoutItsFlagAndNotes(): void
    {
        $document = $this->document(<<<'MD_WRAP'
        ## 2024
        
        ### Q4 2024
        
        | Date | City | Type |
        |---|---|---|
        | 5 Oct | 🇧🇪 Mechelen, Belgium | Mission Day |
        | 12 Oct | 🇩🇪🇨🇭 Konstanz, Germany / Kreuzlingen, Switzerland | MD Lite (upcoming) |
        | 3 Nov | 🇪🇸 Valencia, Spain | Anomaly MD (cancelled) |
        | 16 Nov | 🇺🇸 Augusta (GA), United States | Mission Day (postponed) |
        | 11 Aug | 🇯🇵 Kashihara, Japan | Mission Day (moved to Q4) |
        | 19 Mar | 🇯🇵 Ome, Japan | Mission Day (official notice, postponed from 2020) |
        MD_WRAP, 'mission-days');

        $rows = $document->blocks[2]->attendance;

        self::assertSame('Mission Day: Mechelen, Belgium', $rows[0]?->text);
        self::assertSame('mission-days:2024-10-05:Mechelen, Belgium', $rows[0]->key);
        self::assertSame('MD Lite: Konstanz, Germany / Kreuzlingen, Switzerland', $rows[1]?->text);
        self::assertNull($rows[2]);
        self::assertNull($rows[3]);
        self::assertNull($rows[4]);
        // Held, later than first planned
        self::assertSame('Mission Day: Ome, Japan', $rows[5]?->text);
    }

    public function testOtherTablesAndDatesCannotBeAttended(): void
    {
        $document = $this->document(<<<'MD'
            ## All series

            | Medal | Series | Dates |
            |---|---|---|
            | – | Orion | Apr – Jun 2026 |

            | Date | Site | Role |
            |---|---|---|
            | 12 Nov | Outside a year | – |

            ## 2022

            | Date | Site | Role |
            |---|---|---|
            | 18–28 Nov | Global | – |
            MD, 'anomalies');

        self::assertSame([], $document->blocks[1]->attendance);
        self::assertSame([], $document->blocks[2]->attendance);
        self::assertSame([null], $document->blocks[4]->attendance);
    }

    public function testADocumentFindsARowByItsKey(): void
    {
        $document = $this->document("## 2026\n\n### Orion\n\n| Date | Site | Role |\n|---|---|---|\n| 16 May | Prague | – |\n", 'anomalies');

        self::assertSame('Orion Anomaly: Prague', $document->attendance('anomalies:2026-05-16:Prague')?->text);
        self::assertNull($document->attendance('nonsense'));
    }

    private function document(string $markdown, string $slug): HistoryDocument
    {
        return new HistoryAttendanceResolver()->resolve(new HistoryParser()->parse($markdown), $slug);
    }
}
