<?php

namespace App\Support;

/**
 * The kinds of project an archived capstone is, read from its title and
 * abstract.
 *
 * The archive came from the library's list, which says what each study is
 * about but not which ProjectTags it falls under. This names them from the
 * words the study uses - "web-based", "RFID", "augmented reality" - so every
 * capstone is counted in Analytics. A CaPSA can correct any of them from the
 * card afterwards.
 */
class ArchiveTagGuesser
{
    /**
     * Tag => patterns, most distinctive first: when a study matches more tags
     * than a capstone may have, the specific ones are kept.
     */
    private const RULES = [
        'Blockchain' => ['/blockchain/'],
        'AR / VR' => ['/augmented reality/', '/virtual reality/', '/\b(ar|vr|arsafe)\b/', '/\b3d (model|visuali[sz]ation|tour)/'],
        'Computer Vision' => ['/image (recognition|processing|classification)/', '/face recognition|facial|fingerprint recognition/', '/object detection|disease detection|detection (of|among|using)/', '/computer vision/', '/\bocr\b|optical character/', '/plate (number )?recognition/', '/\byolo|\bcnn\b|convolutional|vgg ?16|resnet|mobilenet/'],
        'Chatbot / NLP' => ['/chat ?bot/', '/conversational/', '/natural language/', '/\bnlp\b/', '/speech|voice/', '/sentiment/', '/translat/'],
        'Game Development' => ['/\bgames?\b/', '/gamifi/'],
        'IoT' => ['/\biot\b/', '/internet of things|internet-enabled?/', '/arduino|raspberry|nodemcu|esp32|microcontroller/', '/sensor/', '/\brfid\b/', '/smart (door|lock|home|bin|farm)/', '/door lock|access control|fingerprint|biometric/', '/weather station|gsm module/'],
        'GIS / Mapping' => ['/\bgis\b/', '/geographic/', '/geo-?tag/', '/\bmapping\b|\bmaps?\b/', '/\bgps\b/', '/location-based|room finder|path ?finding|shortest path/'],
        'Cybersecurity' => ['/cyber/', '/encrypt|cryptograph|steganograph/', '/intrusion|phishing|malware/', '/two-factor|authentication/'],
        'AI / Machine Learning' => ['/machine learning|deep learning|neural network/', '/artificial intelligence|\bai\b|\bai-/', '/predict/', '/\bintelligent\b/', '/naive bayes|decision tree|random forest|k-means|regression|classifier|clustering|gradient boosting/', '/genetic algorithm|constraint satisfaction|\bcnn\b|convolutional|vgg ?16/', '/recommend/'],
        'Decision Support System' => ['/decision support|\bdss\b/', '/recommend/', '/optimi[sz]|genetic algorithm|constraint satisfaction|dijkstra/'],
        'Data Analytics' => ['/analytics|data-driven|data analysis|data mining|business intelligence/', '/forecast/', '/dashboard/'],
        'E-commerce' => ['/marketplace|e-?commerce|online (store|shop|ordering)|\bshop\b/', '/point[- ]of[- ]sale|\bpos\b/'],
        'E-learning' => ['/learning management|\blms\b|e-?learning|online learning/', '/tutorial|reviewer|\bquiz/', '/educational (app|game|tool)/'],
        'Mobile App' => ['/mobile|android|\bios\b|smartphone/', '/\bapp\b/'],
        'Automation' => ['/automat/', '/biometric/', '/\bsms\b|text blast/', '/qr code/'],
        'Desktop Application' => ['/desktop|stand-?alone/'],
        'Web-based' => ['/web[- ]?based|web application|\bweb\b|website|online|progressive web|portal/'],
        'Management System' => ['/management|informationman|information system|records?\b|inventory|monitoring|tracking|scheduling|reservation|enrol?lment|admission|payroll|attendance|appointment|calendar|request system|contact tracing|governance/'],
    ];

    /** When nothing else fits: nearly every archived capstone is one. */
    public const FALLBACK = 'Management System';

    /** @return string[] ProjectTags names, at least one and at most ProjectTags::MAX */
    public static function guess(?string $title, ?string $abstract = null): array
    {
        $text = mb_strtolower(trim(($title ?? '').' '.($abstract ?? '')));
        $found = [];

        foreach (self::RULES as $tag => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text)) {
                    $found[] = $tag;

                    break;
                }
            }
        }

        $found = array_slice(array_values(array_unique($found)), 0, ProjectTags::MAX);

        return $found === [] ? [self::FALLBACK] : $found;
    }
}
