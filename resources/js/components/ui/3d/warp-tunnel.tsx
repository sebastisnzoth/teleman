'use client';

import { useRef } from 'react';
import { Canvas, useFrame } from '@react-three/fiber';
import { Stars } from '@react-three/drei';
import * as THREE from 'three';

function TunnelEffect() {
  const starsRef = useRef<THREE.Group>(null!);

  useFrame((state, delta) => {
    if (starsRef.current) {
         // Move stars towards camera to simulate speed
         starsRef.current.rotation.z += delta * 0.05;
         starsRef.current.position.z += delta * 2;
         
         // Loop animation
         if(starsRef.current.position.z > 20) {
            starsRef.current.position.z = 0;
         }
    }
  });

  return (
    <group ref={starsRef}>
      <Stars 
        radius={100} 
        depth={50} 
        count={5000} 
        factor={4} 
        saturation={0} 
        fade 
        speed={2} 
      />
    </group>
  );
}

export function WarpTunnel({ className = "" }: { className?: string }) {
  return (
    <div className={`absolute inset-0 w-full h-full pointer-events-none mix-blend-screen opacity-40 ${className}`}>
      <Canvas camera={{ position: [0, 0, 1] }}>
        <TunnelEffect />
      </Canvas>
    </div>
  );
}
