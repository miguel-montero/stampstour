<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class HotelResolverTest extends TestCase
{
    private mysqli $conn;

    protected function setUp(): void
    {
        global $conn;
        $this->conn = $conn;
        $this->conn->query("DELETE FROM hoteles WHERE nombre_hotel LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    protected function tearDown(): void
    {
        $this->conn->query("DELETE FROM hoteles WHERE nombre_hotel LIKE 'TEST\\_%' ESCAPE '\\\\'");
    }

    private function insertTestHotel(string $nombre): int
    {
        $stmt = $this->conn->prepare("INSERT INTO hoteles (nombre_hotel) VALUES (?)");
        $stmt->bind_param('s', $nombre);
        $stmt->execute();
        $id = $stmt->insert_id;
        $stmt->close();
        return $id;
    }

    private function hotelesCount(): int
    {
        $result = $this->conn->query("SELECT COUNT(*) c FROM hoteles");
        return (int)$result->fetch_assoc()['c'];
    }

    public function testDirectIdHotelFromSelect(): void
    {
        $id = $this->insertTestHotel('TEST_Direct Select Hotel');
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['id_hotel' => (string)$id]);
        $this->assertSame($id, $id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testNotListedWithCustomTextIsSavedAsManualText(): void
    {
        $before = $this->hotelesCount();
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'not_listed',
            'customHotel' => 'TEST_Some Random Guesthouse',
        ]);
        $this->assertNull($id_hotel);
        $this->assertSame('TEST_Some Random Guesthouse', $hotel_manual);
        $this->assertSame($before, $this->hotelesCount(), 'must not insert into hoteles');
    }

    public function testNotListedWithoutTextResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'not_listed',
            'customHotel' => '   ',
        ]);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testDecideLaterResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, [
            'hotelOption' => 'decide_later',
            'hotel' => 'TEST_Should Be Ignored',
        ]);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testAutocompleteTextMatchingCatalogResolvesToIdHotel(): void
    {
        $id = $this->insertTestHotel('TEST_Aji Hostel Clone');
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['hotel' => 'TEST_Aji Hostel Clone']);
        $this->assertSame($id, $id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testAutocompleteTextNotMatchingCatalogFallsBackToManualText(): void
    {
        $before = $this->hotelesCount();
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['hotel' => 'TEST_Nonexistent Hotel Name']);
        $this->assertNull($id_hotel);
        $this->assertSame('TEST_Nonexistent Hotel Name', $hotel_manual);
        $this->assertSame($before, $this->hotelesCount(), 'must not insert into hoteles');
    }

    public function testAutocompleteTextIsExactMatchNotFuzzyMatch(): void
    {
        $this->insertTestHotel('TEST_Aji Hostel Clone');
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, ['hotel' => 'TEST_Aji']);
        $this->assertNull($id_hotel);
        $this->assertSame('TEST_Aji', $hotel_manual);
    }

    public function testNoHotelFieldsAtAllResolvesToNothing(): void
    {
        [$id_hotel, $hotel_manual] = resolve_hotel_selection($this->conn, []);
        $this->assertNull($id_hotel);
        $this->assertNull($hotel_manual);
    }

    public function testSubmitAndReturnNeverInsertIntoHoteles(): void
    {
        foreach (['submit.php', 'return.php'] as $file) {
            $source = file_get_contents(__DIR__ . '/../' . $file);
            $this->assertNotFalse($source);
            $this->assertStringNotContainsStringIgnoringCase(
                'INSERT INTO hoteles',
                $source,
                "$file must never insert into the hoteles catalog table"
            );
        }
    }
}
