'use client';

import { useRef } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import * as THREE from 'three';

function GridAnimation() {
  const gridRef = useRef<THREE.Group>(null!);

  useFrame((state) => {
    if (gridRef.current) {
        // Infinite scrolling floor effect
        // Move z position based on time, reset every 2 units (grid cell size)
        // Speed = 1
        gridRef.current.position.z = (state.clock.getElapsedTime() * 1.5) % 2;
    }
  });

  return (
    <group ref={gridRef}>
       {/* 
         GridHelper(size, divisions, centerColor, gridColor) 
         Size 60, Divisions 30 => Cell size = 2 
       */}
       <gridHelper 
         args={[60, 30, 0x475569, 0x334155]} 
         position={[0, -2, 0]} 
       />
    </group>
  );
}

export function TechGrid({ className = "" }: { className?: string }) {
  return (
    <div className={`absolute inset-0 w-full h-full pointer-events-none opacity-25 mix-blend-screen ${className}`}>
      <Canvas camera={{ position: [0, 2, 10], fov: 60 }} gl={{ alpha: true, antialias: false }}>
        {/* Fog to fade out the grid into the distance */}
        <fog attach="fog" args={['#000000', 5, 25]} />
        <GridAnimation />
      </Canvas>
    </div>
  );
}
