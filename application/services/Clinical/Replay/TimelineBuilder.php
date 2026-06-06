<?php

class TimelineBuilder
{
    public function build(array $events): array
    {
        return ReplayOrdering::sort($events);
    }
}
