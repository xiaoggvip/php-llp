<?php

declare(strict_types=1);

namespace PhpLLP\Tests\Unit\Embeddings;

use PhpLLP\Embeddings\Distances\CosineDistance;
use PhpLLP\Embeddings\Distances\EuclideanDistance;
use PHPUnit\Framework\TestCase;

class DistanceTest extends TestCase
{
    public function testCosineDistanceIdentical(): void
    {
        $cosine = new CosineDistance();
        $a = [1.0, 0.0, 0.0];
        $b = [1.0, 0.0, 0.0];
        $score = $cosine->calculate($a, $b);
        $this->assertEqualsWithDelta(1.0, $score, 0.0001);
    }

    public function testCosineDistanceOrthogonal(): void
    {
        $cosine = new CosineDistance();
        $a = [1.0, 0.0];
        $b = [0.0, 1.0];
        $score = $cosine->calculate($a, $b);
        $this->assertEqualsWithDelta(0.0, $score, 0.0001);
    }

    public function testCosineDistanceOpposite(): void
    {
        $cosine = new CosineDistance();
        $a = [1.0, 0.0];
        $b = [-1.0, 0.0];
        $score = $cosine->calculate($a, $b);
        $this->assertEqualsWithDelta(-1.0, $score, 0.0001);
    }

    public function testCosineDistanceSimilar(): void
    {
        $cosine = new CosineDistance();
        $a = [1.0, 0.0, 0.0];
        $b = [0.9, 0.1, 0.0];
        $score = $cosine->calculate($a, $b);
        $this->assertGreaterThan(0.9, $score);
    }

    public function testEuclideanDistanceIdentical(): void
    {
        $euclidean = new EuclideanDistance();
        $a = [1.0, 2.0, 3.0];
        $b = [1.0, 2.0, 3.0];
        $score = $euclidean->calculate($a, $b);
        $this->assertEqualsWithDelta(0.0, $score, 0.0001);
    }

    public function testEuclideanDistanceDifferent(): void
    {
        $euclidean = new EuclideanDistance();
        $a = [0.0, 0.0];
        $b = [3.0, 4.0];
        $score = $euclidean->calculate($a, $b);
        $this->assertEqualsWithDelta(5.0, $score, 0.0001);
    }

    public function testEuclideanDistanceSymmetric(): void
    {
        $euclidean = new EuclideanDistance();
        $a = [1.0, 2.0];
        $b = [4.0, 6.0];
        $score1 = $euclidean->calculate($a, $b);
        $score2 = $euclidean->calculate($b, $a);
        $this->assertEqualsWithDelta($score1, $score2, 0.0001);
    }

    public function testCosineDistanceProperties(): void
    {
        $cosine = new CosineDistance();
        $this->assertEquals('cosine', $cosine->getName());
    }

    public function testEuclideanDistanceProperties(): void
    {
        $euclidean = new EuclideanDistance();
        $this->assertEquals('euclidean', $euclidean->getName());
    }
}