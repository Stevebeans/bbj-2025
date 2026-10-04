<?php

namespace BigBrotherJunkies\Data\Stories\Sources;

interface SourceAdapter
{
    /**
     * @param array{show:string,type:string,key:string,trust:string,flair?:string} $source
     * @return array{items:array<int,array<string,mixed>>,error:?string}
     */
    public function fetch(array $source): array;
}
