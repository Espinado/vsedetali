<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    public function run(): void
    {
        $vehicles = [
            ['make' => 'Audi', 'model' => 'A3', 'generation' => '8V', 'year_from' => 2012, 'year_to' => 2020],
            ['make' => 'Audi', 'model' => 'A4', 'generation' => 'B8', 'year_from' => 2009, 'year_to' => 2015],
            ['make' => 'Audi', 'model' => 'A4', 'generation' => 'B9', 'year_from' => 2016, 'year_to' => 2025],
            ['make' => 'Audi', 'model' => 'Q5', 'generation' => 'FY', 'year_from' => 2017, 'year_to' => 2025],

            ['make' => 'BMW', 'model' => '3 Series', 'generation' => 'F30', 'year_from' => 2012, 'year_to' => 2019],
            ['make' => 'BMW', 'model' => '3 Series', 'generation' => 'G20', 'year_from' => 2019, 'year_to' => 2025],
            ['make' => 'BMW', 'model' => '5 Series', 'generation' => 'F10', 'year_from' => 2010, 'year_to' => 2017],
            ['make' => 'BMW', 'model' => '5 Series', 'generation' => 'G30', 'year_from' => 2017, 'year_to' => 2025],
            ['make' => 'BMW', 'model' => 'X5', 'generation' => 'F15', 'year_from' => 2013, 'year_to' => 2018],
            ['make' => 'BMW', 'model' => 'X5', 'generation' => 'G05', 'year_from' => 2018, 'year_to' => 2025],

            ['make' => 'Ford', 'model' => 'Focus', 'generation' => 'III', 'year_from' => 2011, 'year_to' => 2018],
            ['make' => 'Ford', 'model' => 'Focus', 'generation' => 'IV', 'year_from' => 2018, 'year_to' => 2025],
            ['make' => 'Ford', 'model' => 'Mondeo', 'generation' => 'IV', 'year_from' => 2009, 'year_to' => 2014],
            ['make' => 'Ford', 'model' => 'Mondeo', 'generation' => 'V', 'year_from' => 2014, 'year_to' => 2022],
            ['make' => 'Ford', 'model' => 'Kuga', 'generation' => 'II', 'year_from' => 2012, 'year_to' => 2019],
            ['make' => 'Ford', 'model' => 'Kuga', 'generation' => 'III', 'year_from' => 2019, 'year_to' => 2025],

            ['make' => 'Mercedes-Benz', 'model' => 'C-Class', 'generation' => 'W204', 'year_from' => 2009, 'year_to' => 2014],
            ['make' => 'Mercedes-Benz', 'model' => 'C-Class', 'generation' => 'W205', 'year_from' => 2014, 'year_to' => 2021],
            ['make' => 'Mercedes-Benz', 'model' => 'C-Class', 'generation' => 'W206', 'year_from' => 2021, 'year_to' => 2025],
            ['make' => 'Mercedes-Benz', 'model' => 'E-Class', 'generation' => 'W212', 'year_from' => 2009, 'year_to' => 2016],
            ['make' => 'Mercedes-Benz', 'model' => 'E-Class', 'generation' => 'W213', 'year_from' => 2016, 'year_to' => 2025],
            ['make' => 'Mercedes-Benz', 'model' => 'GLC', 'generation' => 'X253', 'year_from' => 2015, 'year_to' => 2022],
            ['make' => 'Mercedes-Benz', 'model' => 'GLC', 'generation' => 'X254', 'year_from' => 2022, 'year_to' => 2025],

            ['make' => 'Nissan', 'model' => 'Qashqai', 'generation' => 'J10', 'year_from' => 2009, 'year_to' => 2013],
            ['make' => 'Nissan', 'model' => 'Qashqai', 'generation' => 'J11', 'year_from' => 2014, 'year_to' => 2021],
            ['make' => 'Nissan', 'model' => 'Qashqai', 'generation' => 'J12', 'year_from' => 2021, 'year_to' => 2025],
            ['make' => 'Nissan', 'model' => 'X-Trail', 'generation' => 'T32', 'year_from' => 2014, 'year_to' => 2022],
            ['make' => 'Nissan', 'model' => 'X-Trail', 'generation' => 'T33', 'year_from' => 2022, 'year_to' => 2025],

            ['make' => 'Opel', 'model' => 'Astra', 'generation' => 'J', 'year_from' => 2009, 'year_to' => 2015],
            ['make' => 'Opel', 'model' => 'Astra', 'generation' => 'K', 'year_from' => 2015, 'year_to' => 2021],
            ['make' => 'Opel', 'model' => 'Astra', 'generation' => 'L', 'year_from' => 2021, 'year_to' => 2025],
            ['make' => 'Opel', 'model' => 'Insignia', 'generation' => 'A', 'year_from' => 2009, 'year_to' => 2017],
            ['make' => 'Opel', 'model' => 'Insignia', 'generation' => 'B', 'year_from' => 2017, 'year_to' => 2022],
            ['make' => 'Opel', 'model' => 'Mokka', 'generation' => 'I', 'year_from' => 2012, 'year_to' => 2019],
            ['make' => 'Opel', 'model' => 'Mokka', 'generation' => 'B', 'year_from' => 2020, 'year_to' => 2025],

            ['make' => 'Peugeot', 'model' => '308', 'generation' => 'I FL', 'year_from' => 2009, 'year_to' => 2013],
            ['make' => 'Peugeot', 'model' => '308', 'generation' => 'II', 'year_from' => 2013, 'year_to' => 2021],
            ['make' => 'Peugeot', 'model' => '308', 'generation' => 'III', 'year_from' => 2021, 'year_to' => 2025],
            ['make' => 'Peugeot', 'model' => '3008', 'generation' => 'I', 'year_from' => 2009, 'year_to' => 2016],
            ['make' => 'Peugeot', 'model' => '3008', 'generation' => 'II', 'year_from' => 2016, 'year_to' => 2023],
            ['make' => 'Peugeot', 'model' => '508', 'generation' => 'I', 'year_from' => 2010, 'year_to' => 2018],
            ['make' => 'Peugeot', 'model' => '508', 'generation' => 'II', 'year_from' => 2018, 'year_to' => 2025],

            ['make' => 'Renault', 'model' => 'Megane', 'generation' => 'III', 'year_from' => 2009, 'year_to' => 2016],
            ['make' => 'Renault', 'model' => 'Megane', 'generation' => 'IV', 'year_from' => 2016, 'year_to' => 2024],
            ['make' => 'Renault', 'model' => 'Scenic', 'generation' => 'III', 'year_from' => 2009, 'year_to' => 2016],
            ['make' => 'Renault', 'model' => 'Scenic', 'generation' => 'IV', 'year_from' => 2016, 'year_to' => 2022],
            ['make' => 'Renault', 'model' => 'Kadjar', 'generation' => 'I', 'year_from' => 2015, 'year_to' => 2022],

            ['make' => 'Skoda', 'model' => 'Octavia', 'generation' => 'II FL', 'year_from' => 2009, 'year_to' => 2013],
            ['make' => 'Skoda', 'model' => 'Octavia', 'generation' => 'III', 'year_from' => 2013, 'year_to' => 2020],
            ['make' => 'Skoda', 'model' => 'Octavia', 'generation' => 'IV', 'year_from' => 2020, 'year_to' => 2025],
            ['make' => 'Skoda', 'model' => 'Superb', 'generation' => 'II', 'year_from' => 2009, 'year_to' => 2015],
            ['make' => 'Skoda', 'model' => 'Superb', 'generation' => 'III', 'year_from' => 2015, 'year_to' => 2024],
            ['make' => 'Skoda', 'model' => 'Kodiaq', 'generation' => 'I', 'year_from' => 2016, 'year_to' => 2025],

            ['make' => 'Toyota', 'model' => 'Corolla', 'generation' => 'E150', 'year_from' => 2009, 'year_to' => 2013],
            ['make' => 'Toyota', 'model' => 'Corolla', 'generation' => 'E180', 'year_from' => 2013, 'year_to' => 2018],
            ['make' => 'Toyota', 'model' => 'Corolla', 'generation' => 'E210', 'year_from' => 2019, 'year_to' => 2025],
            ['make' => 'Toyota', 'model' => 'Camry', 'generation' => 'XV50', 'year_from' => 2011, 'year_to' => 2017],
            ['make' => 'Toyota', 'model' => 'Camry', 'generation' => 'XV70', 'year_from' => 2017, 'year_to' => 2025],
            ['make' => 'Toyota', 'model' => 'RAV4', 'generation' => 'XA40', 'year_from' => 2013, 'year_to' => 2018],
            ['make' => 'Toyota', 'model' => 'RAV4', 'generation' => 'XA50', 'year_from' => 2019, 'year_to' => 2025],

            ['make' => 'Volkswagen', 'model' => 'Golf', 'generation' => 'VI', 'year_from' => 2009, 'year_to' => 2012],
            ['make' => 'Volkswagen', 'model' => 'Golf', 'generation' => 'VII', 'year_from' => 2013, 'year_to' => 2019],
            ['make' => 'Volkswagen', 'model' => 'Golf', 'generation' => 'VIII', 'year_from' => 2020, 'year_to' => 2025],
            ['make' => 'Volkswagen', 'model' => 'Passat', 'generation' => 'B7', 'year_from' => 2010, 'year_to' => 2014],
            ['make' => 'Volkswagen', 'model' => 'Passat', 'generation' => 'B8', 'year_from' => 2015, 'year_to' => 2025],
            ['make' => 'Volkswagen', 'model' => 'Tiguan', 'generation' => 'I FL', 'year_from' => 2009, 'year_to' => 2015],
            ['make' => 'Volkswagen', 'model' => 'Tiguan', 'generation' => 'II', 'year_from' => 2016, 'year_to' => 2025],

            ['make' => 'Volvo', 'model' => 'S60', 'generation' => 'II', 'year_from' => 2010, 'year_to' => 2018],
            ['make' => 'Volvo', 'model' => 'S60', 'generation' => 'III', 'year_from' => 2018, 'year_to' => 2025],
            ['make' => 'Volvo', 'model' => 'V60', 'generation' => 'I', 'year_from' => 2010, 'year_to' => 2018],
            ['make' => 'Volvo', 'model' => 'V60', 'generation' => 'II', 'year_from' => 2018, 'year_to' => 2025],
            ['make' => 'Volvo', 'model' => 'XC60', 'generation' => 'I', 'year_from' => 2009, 'year_to' => 2017],
            ['make' => 'Volvo', 'model' => 'XC60', 'generation' => 'II', 'year_from' => 2017, 'year_to' => 2025],
        ];

        foreach ($vehicles as $data) {
            Vehicle::updateOrCreate(
                [
                    'make' => $data['make'],
                    'model' => $data['model'],
                    'generation' => $data['generation'],
                    'year_from' => $data['year_from'],
                    'year_to' => $data['year_to'],
                ],
                $data
            );
        }

        $products = Product::query()->get()->keyBy('sku');

        $compatibility = [
            'BOS-0986AB4235' => [['Volkswagen', 'Golf'], ['Skoda', 'Octavia'], ['Audi', 'A3']],
            'ATE-24.0136' => [['Volkswagen', 'Passat'], ['Skoda', 'Superb'], ['Audi', 'A4']],
            'TRW-DF7232' => [['Volkswagen', 'Golf'], ['Audi', 'A3'], ['Skoda', 'Octavia']],
            'BOS-0986AB4236' => [['Volkswagen', 'Passat'], ['Audi', 'A4'], ['Skoda', 'Superb']],
            'TRW-BHS1024E' => [['Volkswagen', 'Passat'], ['Skoda', 'Superb'], ['Audi', 'A4']],
            'BOS-0986473002' => [['Volkswagen', 'Golf'], ['Skoda', 'Octavia'], ['Audi', 'A3']],
            'SAC-314821' => [['BMW', '3 Series'], ['Volvo', 'S60'], ['Ford', 'Focus']],
            'SAC-314822' => [['BMW', '5 Series'], ['Volvo', 'V60'], ['Ford', 'Mondeo']],
            'LEM-2567001' => [['Ford', 'Focus'], ['Opel', 'Astra'], ['Renault', 'Megane']],
            'LEM-3567001' => [['Ford', 'Kuga'], ['Opel', 'Insignia'], ['Renault', 'Scenic']],
            'NGK-BCPR6ES' => [['Toyota', 'Corolla'], ['Nissan', 'Qashqai'], ['Renault', 'Megane']],
            'DEN-PK20PR-P8' => [['Toyota', 'Camry'], ['Toyota', 'RAV4'], ['Nissan', 'X-Trail']],
            'BOS-AF3027' => [['Volkswagen', 'Tiguan'], ['Skoda', 'Kodiaq'], ['Audi', 'Q5']],
            'VAL-025170' => [['Volkswagen', 'Golf'], ['Skoda', 'Octavia'], ['Toyota', 'Corolla']],
            'BOS-1987949668' => [['Volkswagen', 'Golf'], ['Skoda', 'Octavia'], ['Audi', 'A3']],
            'ATE-K015670XS' => [['Renault', 'Megane'], ['Ford', 'Focus'], ['Opel', 'Astra']],
            'BOS-S474AH' => [['BMW', '3 Series'], ['Mercedes-Benz', 'C-Class'], ['Volkswagen', 'Passat']],
            'VAL-SS70AH' => [['Volvo', 'XC60'], ['Audi', 'Q5'], ['Toyota', 'RAV4']],
            'VAL-438185' => [['Renault', 'Scenic'], ['Nissan', 'Qashqai'], ['Ford', 'Mondeo']],
            'BOS-0121715001' => [['BMW', '5 Series'], ['Mercedes-Benz', 'E-Class'], ['Volvo', 'V60']],
        ];

        foreach ($compatibility as $sku => $pairs) {
            $product = $products->get($sku);

            if (! $product) {
                continue;
            }

            $vehicleIds = Vehicle::query()
                ->where(function ($query) use ($pairs) {
                    foreach ($pairs as [$make, $model]) {
                        $query->orWhere(function ($subQuery) use ($make, $model) {
                            $subQuery->where('make', $make)->where('model', $model);
                        });
                    }
                })
                ->pluck('id')
                ->all();

            if ($vehicleIds !== []) {
                $product->vehicles()->syncWithoutDetaching($vehicleIds);
            }
        }
    }
}
